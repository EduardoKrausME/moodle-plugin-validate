#!/usr/bin/env python3
"""Normalize version.php for easier release maintenance.

This is a formatter, not a Moodle validation rule. It moves the existing
$plugin->release and $plugin->version assignments to the top, preserves every
other assignment in its original relative order, and removes a simple
$plugin->supported assignment as a project cleanup convention.
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path


ASSIGNMENT_RE = re.compile(
    r"^(?P<indent>\s*)\$plugin->(?P<name>[A-Za-z_][A-Za-z0-9_]*)\s*=\s*(?P<value>.+?);(?P<tail>\s*(?://.*|#.*)?)$"
)


class VersionNormalizationError(RuntimeError):
    """Raised when version.php cannot be reformatted safely."""


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Format version.php with release first and version second, without "
            "turning the convention into a validation rule."
        )
    )
    parser.add_argument("plugin", type=Path, help="Path to the Moodle plugin root.")
    parser.add_argument(
        "--check",
        action="store_true",
        help="Report whether version.php would be reformatted without writing it.",
    )
    return parser.parse_args()


def normalize_content(content: str, *, filename: Path) -> str:
    had_final_newline = content.endswith("\n")
    lines = content.splitlines()

    assignments: list[tuple[int, str]] = []
    for index, line in enumerate(lines):
        match = ASSIGNMENT_RE.match(line)
        if match:
            assignments.append((index, match.group("name")))

    if not assignments:
        raise VersionNormalizationError(
            f"{filename}: no $plugin->... assignments were found."
        )

    indexes_by_name: dict[str, list[int]] = {}
    for index, name in assignments:
        indexes_by_name.setdefault(name, []).append(index)

    for required in ("release", "version"):
        indexes = indexes_by_name.get(required, [])
        if not indexes:
            raise VersionNormalizationError(
                f"{filename}: cannot format because $plugin->{required} is missing."
            )
        if len(indexes) != 1:
            raise VersionNormalizationError(
                f"{filename}: cannot format because $plugin->{required} appears "
                "more than once."
            )

    release_index = indexes_by_name["release"][0]
    version_index = indexes_by_name["version"][0]
    first_assignment_index = assignments[0][0]

    release_line = lines[release_index]
    version_line = lines[version_index]

    # $plugin->supported is not part of this project's preferred version.php.
    # Remove only simple one-line assignments that were parsed safely.
    supported_indexes = indexes_by_name.get("supported", [])

    removed_indexes = {release_index, version_index, *supported_indexes}
    for index in sorted(removed_indexes, reverse=True):
        del lines[index]

    removed_before = sum(1 for index in removed_indexes if index < first_assignment_index)
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
            "CHANGE: version.php would be reformatted with release first and "
            "version second.",
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
