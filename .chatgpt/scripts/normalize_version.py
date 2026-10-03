#!/usr/bin/env python3
"""Normalize the ordering and version-policy metadata in version.php."""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path


ASSIGNMENT_RE = re.compile(
    r"^(?P<indent>\s*)\$plugin->(?P<name>[A-Za-z_][A-Za-z0-9_]*)\s*=\s*(?P<value>.+?);(?P<tail>\s*(?://.*|#.*)?)$"
)
ARRAY_START_RE = re.compile(r"^(?:\[|array\s*\()", re.IGNORECASE)


class VersionNormalizationError(RuntimeError):
    """Raised when version.php cannot be normalized safely."""


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Normalize Moodle version.php so release and version are the first "
            "plugin properties and unsupported version-range arrays are absent."
        )
    )
    parser.add_argument("plugin", type=Path, help="Path to the Moodle plugin root.")
    parser.add_argument(
        "--check",
        action="store_true",
        help="Report required changes without rewriting version.php.",
    )
    return parser.parse_args()


def normalize_content(content: str, *, filename: Path) -> str:
    had_final_newline = content.endswith("\n")
    lines = content.splitlines()

    assignments: list[tuple[int, str, str]] = []
    for index, line in enumerate(lines):
        match = ASSIGNMENT_RE.match(line)
        if match:
            assignments.append((index, match.group("name"), match.group("value").strip()))

    if not assignments:
        raise VersionNormalizationError(
            f"{filename}: no $plugin->... assignments were found."
        )

    by_name: dict[str, list[tuple[int, str]]] = {}
    for index, name, value in assignments:
        by_name.setdefault(name, []).append((index, value))

    for required in ("release", "version"):
        occurrences = by_name.get(required, [])
        if not occurrences:
            raise VersionNormalizationError(
                f"{filename}: missing required $plugin->{required} assignment."
            )
        if len(occurrences) != 1:
            raise VersionNormalizationError(
                f"{filename}: $plugin->{required} must be assigned exactly once."
            )

    if "supported" in by_name:
        raise VersionNormalizationError(
            f"{filename}: remove $plugin->supported. This project does not use "
            "Moodle-version range arrays such as [405, 505]."
        )

    for index, value in by_name.get("requires", []):
        if ARRAY_START_RE.match(value):
            raise VersionNormalizationError(
                f"{filename}:{index + 1}: $plugin->requires must be a scalar "
                "numeric Moodle version, never an array."
            )

    release_index = by_name["release"][0][0]
    version_index = by_name["version"][0][0]
    first_assignment_index = assignments[0][0]

    release_line = lines[release_index]
    version_line = lines[version_index]

    # Reordering is deterministic and does not alter the assignment text itself.
    # Remove from bottom to top so indices remain valid.
    for index in sorted((release_index, version_index), reverse=True):
        del lines[index]

    # Removing an earlier line shifts the original insertion point.
    removed_before = sum(
        1 for index in (release_index, version_index) if index < first_assignment_index
    )
    insertion_index = first_assignment_index - removed_before

    lines[insertion_index:insertion_index] = [release_line, version_line]

    normalized = "\n".join(lines)
    if had_final_newline:
        normalized += "\n"
    return normalized


def main() -> int:
    args = parse_args()
    plugin = args.plugin.expanduser().resolve()
    version_file = plugin / "version.php"

    if not version_file.is_file():
        print(f"ERROR: missing {version_file}", file=sys.stderr)
        return 2

    try:
        original = version_file.read_text(encoding="utf-8")
        normalized = normalize_content(original, filename=version_file)
    except (OSError, UnicodeError, VersionNormalizationError) as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 1

    if normalized == original:
        print(f"OK: {version_file}")
        return 0

    if args.check:
        print(
            "ERROR: version.php is not normalized. The first two $plugin "
            "assignments must be release and version.",
            file=sys.stderr,
        )
        return 1

    try:
        version_file.write_text(normalized, encoding="utf-8")
    except OSError as exc:
        print(f"ERROR: cannot write {version_file}: {exc}", file=sys.stderr)
        return 1

    print(f"UPDATED: {version_file}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
