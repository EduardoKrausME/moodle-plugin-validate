#!/usr/bin/env python3
"""Run the mandatory Moodle plugin finishing steps."""

from __future__ import annotations

import argparse
import subprocess
import sys
from pathlib import Path


ROOT = Path(__file__).resolve().parent

# Execution order is intentional. Auto-fix deterministic formatting first, then
# execute the project's complete canonical validation suite.
STEPS = [
    ROOT / "scripts" / "normalize_php_headers.py",
    ROOT / "scripts" / "normalize_version.py",
    ROOT / "scripts" / "normalize_lang.py",
    ROOT / "scripts" / "validate_all.py",
]


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Run deterministic finishing steps against a Moodle plugin."
    )
    parser.add_argument(
        "plugin",
        type=Path,
        help="Path to the Moodle plugin root.",
    )
    parser.add_argument(
        "--check",
        action="store_true",
        help="Check files and run validators without rewriting the plugin.",
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    plugin = args.plugin.expanduser().resolve()

    if not plugin.is_dir():
        print(f"ERROR: plugin directory does not exist: {plugin}", file=sys.stderr)
        return 2

    if not (plugin / "version.php").is_file():
        print(
            f"ERROR: {plugin} does not look like a Moodle plugin: version.php is missing.",
            file=sys.stderr,
        )
        return 2

    for step in STEPS:
        command = [sys.executable, str(step)]
        if args.check:
            command.append("--check")
        command.append(str(plugin))

        print(f"\n==> {step.name}")
        result = subprocess.run(command, check=False)
        if result.returncode != 0:
            print(
                f"ERROR: finishing step {step.name} failed with exit code "
                f"{result.returncode}.",
                file=sys.stderr,
            )
            return result.returncode

    print("\nAll Moodle plugin finishing steps completed successfully.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
