#!/usr/bin/env python3
"""Normalize Moodle lang/en and lang/pt_br PHP files.

The formatter intentionally accepts only ordinary one-line Moodle
$string['key'] = 'value'; assignments. If it finds a $string assignment that
it cannot parse safely, it exits with an error instead of rewriting the file.
"""

from __future__ import annotations

import argparse
import re
import sys
from dataclasses import dataclass
from pathlib import Path


LANGUAGES = ("en", "pt_br")

ASSIGNMENT_RE = re.compile(
    r"""^(?P<indent>\s*)\$string\[
        (?P<keyquote>['"])
        (?P<key>(?:\\.|(?! (?P=keyquote) ).)*)
        (?P=keyquote)
        \]\s*=\s*
        (?P<valuequote>['"])
        (?P<value>(?:\\.|(?! (?P=valuequote) ).)*)
        (?P=valuequote)
        ;(?P<tail>\s*(?://.*|\#.*)?)$
    """,
    re.VERBOSE,
)


@dataclass(frozen=True)
class Entry:
    key: str
    line: str
    leading: tuple[str, ...]


class NormalizationError(RuntimeError):
    """Raised when a language file cannot be rewritten safely."""


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Sort Moodle language strings alphabetically and enforce single quotes "
            "in lang/en and lang/pt_br."
        )
    )
    parser.add_argument(
        "plugin",
        type=Path,
        help="Path to the Moodle plugin root.",
    )
    parser.add_argument(
        "--check",
        action="store_true",
        help="Return an error if a file would change instead of rewriting it.",
    )
    return parser.parse_args()


def decode_php_string(raw: str, quote: str, *, filename: Path, key: str) -> str:
    """Decode the limited PHP string syntax used by Moodle language files."""
    output: list[str] = []
    index = 0

    while index < len(raw):
        char = raw[index]
        if char != "\\":
            output.append(char)
            index += 1
            continue

        if index + 1 >= len(raw):
            raise NormalizationError(
                f"{filename}: unterminated escape sequence in $string[{key!r}]."
            )

        escaped = raw[index + 1]

        if quote == "'":
            if escaped in ("\\", "'"):
                output.append(escaped)
            else:
                # PHP single-quoted strings preserve unknown backslash escapes.
                output.append("\\")
                output.append(escaped)
            index += 2
            continue

        # Double-quoted input is converted into a literal single-quoted Moodle
        # language string. Moodle placeholders such as {$a} therefore remain
        # literal text for get_string() to substitute later.
        simple = {
            "\\": "\\",
            '"': '"',
            "$": "$",
        }
        if escaped in simple:
            output.append(simple[escaped])
            index += 2
            continue

        controls = {
            "n": "\n",
            "r": "\r",
        }
        if escaped in controls:
            raise NormalizationError(
                f"{filename}: $string[{key!r}] uses \\{escaped} inside a "
                "double-quoted value. Convert that value manually before running "
                "the normalizer so its runtime meaning is not changed."
            )

        # Unknown escapes in PHP double-quoted strings keep the backslash.
        output.append("\\")
        output.append(escaped)
        index += 2

    return "".join(output)


def encode_single_quoted(value: str) -> str:
    return value.replace("\\", "\\\\").replace("'", "\\'")


def normalize_assignment(line: str, *, filename: Path) -> tuple[str, str]:
    match = ASSIGNMENT_RE.match(line)
    if not match:
        raise NormalizationError(
            f"{filename}: unsupported $string assignment: {line.strip()}"
        )

    key = decode_php_string(
        match.group("key"),
        match.group("keyquote"),
        filename=filename,
        key="<key>",
    )
    value = decode_php_string(
        match.group("value"),
        match.group("valuequote"),
        filename=filename,
        key=key,
    )

    normalized = (
        f"{match.group('indent')}$string['{encode_single_quoted(key)}'] = "
        f"'{encode_single_quoted(value)}';{match.group('tail')}"
    )
    return key, normalized


def is_safe_trivia(lines: list[str], *, filename: Path) -> None:
    """Allow only whitespace and comments between language assignments."""
    in_block_comment = False

    for line in lines:
        stripped = line.strip()
        if not stripped:
            continue

        if in_block_comment:
            if "*/" in stripped:
                in_block_comment = False
            continue

        if stripped.startswith(("//", "#")):
            continue

        if stripped.startswith("/*"):
            if "*/" not in stripped:
                in_block_comment = True
            continue

        raise NormalizationError(
            f"{filename}: executable or unsupported content appears between "
            f"$string assignments: {stripped}"
        )

    if in_block_comment:
        raise NormalizationError(f"{filename}: unterminated block comment.")


def normalize_content(content: str, *, filename: Path) -> str:
    had_final_newline = content.endswith("\n")
    lines = content.splitlines()

    assignment_indexes = [
        index for index, line in enumerate(lines) if "$string[" in line
    ]
    if not assignment_indexes:
        return content

    first = assignment_indexes[0]
    last = assignment_indexes[-1]
    prefix = lines[:first]
    suffix = lines[last + 1 :]

    entries: list[Entry] = []
    previous_assignment = first - 1
    seen: set[str] = set()

    for index in assignment_indexes:
        between = lines[previous_assignment + 1 : index]
        if index == first:
            between = []
        else:
            is_safe_trivia(between, filename=filename)

        key, normalized_line = normalize_assignment(lines[index], filename=filename)
        if key in seen:
            raise NormalizationError(
                f"{filename}: duplicate language key {key!r}."
            )
        seen.add(key)

        entries.append(
            Entry(
                key=key,
                line=normalized_line,
                leading=tuple(between),
            )
        )
        previous_assignment = index

    # Verify there is no unsupported $string syntax hidden between assignments.
    for index in range(first, last + 1):
        if "$string[" in lines[index] and index not in assignment_indexes:
            raise NormalizationError(
                f"{filename}: unsupported $string syntax on line {index + 1}."
            )

    entries.sort(key=lambda entry: (entry.key.casefold(), entry.key))

    output: list[str] = list(prefix)
    for entry in entries:
        output.extend(entry.leading)
        output.append(entry.line)
    output.extend(suffix)

    normalized = "\n".join(output)
    if had_final_newline:
        normalized += "\n"
    return normalized


def normalize_file(path: Path, *, check: bool) -> tuple[bool, str]:
    original = path.read_text(encoding="utf-8")
    normalized = normalize_content(original, filename=path)

    if normalized == original:
        return False, f"OK: {path}"

    if check:
        return True, f"ERROR: {path} is not normalized."

    path.write_text(normalized, encoding="utf-8")
    return True, f"UPDATED: {path}"


def main() -> int:
    args = parse_args()
    plugin = args.plugin.expanduser().resolve()

    if not plugin.is_dir():
        print(f"ERROR: plugin directory does not exist: {plugin}", file=sys.stderr)
        return 2

    wrong_locale = plugin / "lang" / "pt-br"
    if wrong_locale.exists():
        print(
            f"ERROR: invalid Moodle locale directory {wrong_locale}; use lang/pt_br.",
            file=sys.stderr,
        )
        return 1

    changed = False
    processed = 0

    try:
        for language in LANGUAGES:
            directory = plugin / "lang" / language
            if not directory.is_dir():
                print(f"SKIP: {directory} does not exist.")
                continue

            files = sorted(directory.glob("*.php"))
            if not files:
                print(f"SKIP: no PHP language files found in {directory}.")
                continue

            for path in files:
                file_changed, message = normalize_file(path, check=args.check)
                processed += 1
                changed = changed or file_changed
                print(message)

    except (OSError, UnicodeError, NormalizationError) as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 1

    if args.check and changed:
        return 1

    print(
        f"Language normalization complete: {processed} file(s) processed"
        + (", changes applied." if changed and not args.check else ".")
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
