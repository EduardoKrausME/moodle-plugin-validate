#!/usr/bin/env python3
"""Run every validator registered by moodle-plugin-validate.

The PHP Validator class remains the source of truth. This Python finishing step
executes the project's JSON CLI, so every rule currently registered in
src/Validator.php is applied automatically, including rules added in the future.
"""

from __future__ import annotations

import argparse
import json
import re
import shutil
import subprocess
import sys
from pathlib import Path
from typing import Any


CHATGPT_DIR = Path(__file__).resolve().parent.parent
PROJECT_ROOT = CHATGPT_DIR.parent
CLI = PROJECT_ROOT / "bin" / "moodle-string-validate"
VALIDATOR_SOURCE = PROJECT_ROOT / "src" / "Validator.php"

RULE_RE = re.compile(r"new\s+([A-Za-z_][A-Za-z0-9_]*)Rule\s*\(\s*\)")


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Run all moodle-plugin-validate rules against a Moodle plugin."
    )
    parser.add_argument(
        "plugin",
        type=Path,
        help="Path to the Moodle plugin root.",
    )
    parser.add_argument(
        "--check",
        action="store_true",
        help=(
            "Accepted for compatibility with the finishing runner. "
            "Validation itself never rewrites plugin files."
        ),
    )
    return parser.parse_args()


def registered_rules() -> list[str]:
    """Return the rule classes currently registered in src/Validator.php."""
    try:
        source = VALIDATOR_SOURCE.read_text(encoding="utf-8")
    except OSError as exc:
        raise RuntimeError(f"cannot read {VALIDATOR_SOURCE}: {exc}") from exc

    rules = RULE_RE.findall(source)
    if not rules:
        raise RuntimeError(
            "no validators were found in src/Validator.php; refusing to run "
            "because validation coverage cannot be confirmed."
        )
    return rules


def languages_to_validate(plugin: Path) -> list[str]:
    """Validate English and Brazilian Portuguese when those locales exist."""
    languages: list[str] = []

    for language in ("en", "pt_br"):
        directory = plugin / "lang" / language
        if directory.is_dir():
            languages.append(language)

    # The core validator requires one language catalog. English is Moodle's
    # canonical base language, so its absence should still be reported by the
    # validator instead of silently skipping validation.
    if not languages:
        languages.append("en")

    return languages


def run_validator(plugin: Path, language: str) -> tuple[int, dict[str, Any]]:
    php = shutil.which("php")
    if php is None:
        raise RuntimeError(
            "PHP CLI was not found in PATH. moodle-plugin-validate requires PHP "
            "to execute its canonical validators."
        )

    if not CLI.is_file():
        raise RuntimeError(f"validator CLI does not exist: {CLI}")

    command = [
        php,
        str(CLI),
        str(plugin),
        f"--lang={language}",
        "--format=json",
    ]
    result = subprocess.run(
        command,
        check=False,
        text=True,
        capture_output=True,
    )

    if result.stderr.strip():
        print(result.stderr.rstrip(), file=sys.stderr)

    try:
        payload = json.loads(result.stdout)
    except json.JSONDecodeError as exc:
        output = result.stdout.strip()
        if output:
            print(output, file=sys.stderr)
        raise RuntimeError(
            f"validator returned invalid JSON for language {language}: {exc}"
        ) from exc

    return result.returncode, payload


def print_check(check: dict[str, Any]) -> None:
    status = str(check.get("status", "error")).upper()
    rule = str(check.get("rule", "unknown"))
    file = str(check.get("file", ""))
    line = check.get("line", 0)
    message = str(check.get("message", ""))

    location = file
    if file and line:
        location = f"{file}:{line}"

    if location:
        print(f"{status:7} [{rule}] {location} - {message}")
    else:
        print(f"{status:7} [{rule}] {message}")

    if status in {"ERROR", "WARNING"}:
        explanation = str(check.get("explanation", "")).strip()
        how_to_fix = str(check.get("howToFix", "")).strip()
        if explanation:
            print(f"        Why: {explanation}")
        if how_to_fix:
            print(f"        Fix: {how_to_fix}")


def print_payload(language: str, payload: dict[str, Any]) -> None:
    print(f"\nValidation language: {language}")

    runtime_error = payload.get("runtimeError")
    if isinstance(runtime_error, dict):
        message = runtime_error.get("message", "Unknown runtime error")
        print(f"ERROR   [runtime] {message}")
        return

    for group in payload.get("groups", []):
        if not isinstance(group, dict):
            continue
        for check in group.get("checks", []):
            if isinstance(check, dict):
                print_check(check)

    summary = payload.get("summary", {})
    if isinstance(summary, dict):
        print(
            "Summary: "
            f"{summary.get('ok', 0)} OK, "
            f"{summary.get('warnings', 0)} warning(s), "
            f"{summary.get('errors', 0)} error(s)."
        )


def main() -> int:
    args = parse_args()
    plugin = args.plugin.expanduser().resolve()

    if not plugin.is_dir():
        print(f"ERROR: plugin directory does not exist: {plugin}", file=sys.stderr)
        return 2

    try:
        rules = registered_rules()
    except RuntimeError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 2

    print(
        f"Running all {len(rules)} validators registered in "
        "src/Validator.php:"
    )
    print("  " + ", ".join(rules))

    failed = False
    runtime_failed = False

    for language in languages_to_validate(plugin):
        try:
            returncode, payload = run_validator(plugin, language)
        except RuntimeError as exc:
            print(f"ERROR: {exc}", file=sys.stderr)
            return 2

        print_payload(language, payload)

        if returncode == 2:
            runtime_failed = True
        elif returncode != 0:
            failed = True

    if runtime_failed:
        return 2
    if failed:
        return 1

    print("\nAll registered Moodle plugin validators passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
