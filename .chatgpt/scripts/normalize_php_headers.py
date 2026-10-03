#!/usr/bin/env python3
"""Normalize Moodle PHP file headers.

This is a formatter, not a validator rule. Every first-party PHP file receives
the complete Moodle GPL boilerplate followed by a file docblock containing the
component package, copyright and GPLv3+ license metadata.
"""

from __future__ import annotations

import argparse
import re
import sys
from datetime import datetime
from pathlib import Path


EXCLUDED_DIRS = {
    ".git",
    ".idea",
    ".vscode",
    "node_modules",
    "thirdparty",
    "third_party",
    "vendor",
}

COMPONENT_RE = re.compile(
    r"""\$plugin->component\s*=\s*(['"])(?P<component>[a-z][a-z0-9_]*_[a-z0-9_]+)\1\s*;"""
)
VERSION_RE = re.compile(r"\$plugin->version\s*=\s*(?P<version>\d+)\s*;")
COPYRIGHT_RE = re.compile(r"^\s*\*\s*@copyright\s+(?P<value>.+?)\s*$", re.MULTILINE)

HEADER_MARKERS = (
    "This file is part of Moodle",
    "Moodle is free software",
    "GNU General Public License",
    "along with Moodle",
)

GPL_HEADER = """// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>."""


class HeaderNormalizationError(RuntimeError):
    """Raised when a PHP header cannot be rebuilt safely."""


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Rewrite first-party PHP files with the complete Moodle GPL header "
            "and file-level package/copyright/license docblock."
        )
    )
    parser.add_argument("plugin", type=Path, help="Path to the Moodle plugin root.")
    parser.add_argument(
        "--check",
        action="store_true",
        help="Report files that would be changed without rewriting them.",
    )
    return parser.parse_args()


def is_excluded(path: Path, plugin: Path) -> bool:
    try:
        relative = path.relative_to(plugin)
    except ValueError:
        return True

    return any(part.lower() in EXCLUDED_DIRS for part in relative.parts[:-1])


def read_text(path: Path) -> str:
    try:
        return path.read_text(encoding="utf-8")
    except (OSError, UnicodeError) as exc:
        raise HeaderNormalizationError(f"cannot read {path}: {exc}") from exc


def parse_component(version_file: Path) -> str | None:
    content = read_text(version_file)
    match = COMPONENT_RE.search(content)
    return match.group("component") if match else None


def component_roots(plugin: Path) -> list[tuple[Path, str]]:
    roots: list[tuple[Path, str]] = []

    for version_file in plugin.rglob("version.php"):
        if not version_file.is_file() or is_excluded(version_file, plugin):
            continue

        component = parse_component(version_file)
        if component:
            roots.append((version_file.parent.resolve(), component))

    root_component = parse_component(plugin / "version.php")
    if root_component is None:
        raise HeaderNormalizationError(
            f"{plugin / 'version.php'} does not contain a literal $plugin->component."
        )

    root = plugin.resolve()
    if not any(path == root for path, _component in roots):
        roots.append((root, root_component))

    # Deepest component first so bundled subplugins use their own package.
    roots.sort(key=lambda item: len(item[0].parts), reverse=True)
    return roots


def nearest_component(path: Path, roots: list[tuple[Path, str]]) -> tuple[Path, str]:
    resolved = path.resolve()

    for root, component in roots:
        try:
            resolved.relative_to(root)
            return root, component
        except ValueError:
            continue

    raise HeaderNormalizationError(f"cannot resolve Moodle component for {path}.")


def extract_copyrights(content: str) -> list[str]:
    values: list[str] = []
    for match in COPYRIGHT_RE.finditer(content):
        value = match.group("value").strip()
        if value and value not in values:
            values.append(value)
    return values


def version_year(component_root: Path) -> int:
    version_file = component_root / "version.php"
    if version_file.is_file():
        match = VERSION_RE.search(read_text(version_file))
        if match:
            value = match.group("version")
            if len(value) >= 4:
                year = int(value[:4])
                if 2000 <= year <= 2100:
                    return year

    return datetime.now().year


def default_copyright(component_root: Path, plugin: Path) -> str:
    version_file = component_root / "version.php"
    if version_file.is_file():
        copyrights = extract_copyrights(read_text(version_file))
        if copyrights:
            return copyrights[0]

    # Reuse this project's own copyright when it already exists, but do not
    # accidentally turn a third-party file's author into the plugin default.
    for candidate in sorted(component_root.rglob("*.php")):
        if not candidate.is_file() or is_excluded(candidate, plugin):
            continue
        copyrights = extract_copyrights(read_text(candidate))
        for value in copyrights:
            if "Eduardo Kraus" in value:
                return value

    return (
        f"{version_year(component_root)} Eduardo Kraus "
        "{@link https://eduardokraus.com}"
    )


def find_docblock_end(lines: list[str], start: int, filename: Path) -> int:
    for index in range(start, len(lines)):
        if "*/" in lines[index]:
            return index + 1
    raise HeaderNormalizationError(
        f"{filename}: unterminated leading PHPDoc block; refusing to rewrite it."
    )


def docblock_metadata(block: str) -> bool:
    return any(tag in block for tag in ("@package", "@copyright", "@license"))


def next_code_line(lines: list[str], start: int) -> str:
    for line in lines[start:]:
        if line.strip():
            return line.strip()
    return ""


def looks_like_artifact_docblock(lines: list[str], end: int) -> bool:
    following = next_code_line(lines, end)
    return re.match(
        r"^(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\b",
        following,
    ) is not None


def extract_description(block_lines: list[str]) -> str | None:
    for line in block_lines:
        stripped = line.strip()
        if not stripped.startswith("*"):
            continue

        text = stripped.lstrip("*").strip()
        if not text or text.startswith("@") or text in {"/", "*/"}:
            continue

        return text

    return None


def strip_existing_header(
    after_php: str,
    *,
    filename: Path,
) -> tuple[str, str | None, list[str]]:
    """Remove only the leading Moodle/file header, preserving executable code."""
    lines = after_php.splitlines()
    index = 0
    description: str | None = None
    copyrights: list[str] = []

    while index < len(lines) and not lines[index].strip():
        index += 1

    # Remove a complete or partial Moodle // GPL header when it starts at the top.
    if index < len(lines) and lines[index].lstrip().startswith("//"):
        cursor = index
        comment_lines: list[str] = []
        while cursor < len(lines):
            stripped = lines[cursor].strip()
            if stripped.startswith("//") or not stripped:
                comment_lines.append(lines[cursor])
                cursor += 1
                continue
            break

        joined = "\n".join(comment_lines)
        if any(marker in joined for marker in HEADER_MARKERS):
            index = cursor
            while index < len(lines) and not lines[index].strip():
                index += 1

    # Remove the existing file-level metadata docblock. If the block is attached
    # directly to a class/interface/trait/enum, preserve it as artifact PHPDoc.
    if index < len(lines) and lines[index].lstrip().startswith("/**"):
        end = find_docblock_end(lines, index, filename)
        block_lines = lines[index:end]
        block = "\n".join(block_lines)

        if docblock_metadata(block) and not looks_like_artifact_docblock(lines, end):
            description = extract_description(block_lines)
            copyrights = extract_copyrights(block)
            index = end
            while index < len(lines) and not lines[index].strip():
                index += 1

    body = "\n".join(lines[index:])
    return body, description, copyrights


def file_description(
    path: Path,
    component_root: Path,
    component: str,
    existing: str | None,
) -> str:
    if existing:
        return existing.rstrip(".")

    relative = path.relative_to(component_root).as_posix()

    common = {
        "version.php": "Plugin version information",
        "lib.php": "Plugin library functions",
        "settings.php": "Plugin settings",
        "db/access.php": "Plugin capabilities",
        "db/caches.php": "Plugin cache definitions",
        "db/install.php": "Plugin installation steps",
        "db/messages.php": "Plugin message providers",
        "db/upgrade.php": "Plugin upgrade steps",
    }
    if relative in common:
        return common[relative]

    if relative.startswith("lang/"):
        return f"Language strings for {component}"

    return f"{relative} for {component}"


def build_docblock(
    description: str,
    component: str,
    copyrights: list[str],
) -> str:
    lines = [
        "/**",
        f" * {description}.",
        " *",
        f" * @package    {component}",
    ]

    for value in copyrights:
        lines.append(f" * @copyright  {value}")

    lines.extend(
        [
            " * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later",
            " */",
        ]
    )
    return "\n".join(lines)


def normalize_content(
    content: str,
    *,
    path: Path,
    component_root: Path,
    component: str,
    fallback_copyright: str,
) -> str:
    open_index = content.find("<?php")
    if open_index < 0:
        raise HeaderNormalizationError(f"{path}: PHP file does not contain <?php.")

    prefix = content[:open_index]
    after_php = content[open_index + len("<?php"):]

    body, existing_description, existing_copyrights = strip_existing_header(
        after_php,
        filename=path,
    )

    all_existing_copyrights = extract_copyrights(content)
    copyrights = existing_copyrights or all_existing_copyrights or [fallback_copyright]

    description = file_description(
        path,
        component_root,
        component,
        existing_description,
    )
    docblock = build_docblock(description, component, copyrights)

    body = body.lstrip("\n")
    rebuilt = prefix + "<?php\n" + GPL_HEADER + "\n\n" + docblock

    if body:
        rebuilt += "\n\n" + body

    rebuilt = rebuilt.rstrip("\n") + "\n"
    return rebuilt


def php_files(plugin: Path) -> list[Path]:
    files = []
    for path in plugin.rglob("*.php"):
        if path.is_file() and not is_excluded(path, plugin):
            files.append(path)
    return sorted(files)


def main() -> int:
    args = parse_args()
    plugin = args.plugin.expanduser().resolve()

    if not plugin.is_dir():
        print(f"ERROR: plugin directory does not exist: {plugin}", file=sys.stderr)
        return 2

    if not (plugin / "version.php").is_file():
        print(f"ERROR: missing {plugin / 'version.php'}", file=sys.stderr)
        return 2

    try:
        roots = component_roots(plugin)
    except HeaderNormalizationError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 1

    fallback_by_root: dict[Path, str] = {}
    changed = 0
    processed = 0

    try:
        for path in php_files(plugin):
            component_root, component = nearest_component(path, roots)

            if component_root not in fallback_by_root:
                fallback_by_root[component_root] = default_copyright(
                    component_root,
                    plugin,
                )

            original = read_text(path)
            normalized = normalize_content(
                original,
                path=path,
                component_root=component_root,
                component=component,
                fallback_copyright=fallback_by_root[component_root],
            )
            processed += 1

            if normalized == original:
                print(f"OK: {path}")
                continue

            changed += 1
            if args.check:
                print(f"CHANGE: {path}")
                continue

            try:
                path.write_text(normalized, encoding="utf-8")
            except OSError as exc:
                raise HeaderNormalizationError(
                    f"cannot write {path}: {exc}"
                ) from exc

            print(f"UPDATED: {path}")

    except HeaderNormalizationError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 1

    if args.check and changed:
        print(
            f"PHP header check: {changed} of {processed} file(s) would change.",
            file=sys.stderr,
        )
        return 1

    print(
        f"PHP header normalization complete: {processed} file(s) processed, "
        f"{changed} changed."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
