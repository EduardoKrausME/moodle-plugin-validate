# Moodle plugin finishing instructions

These instructions are mandatory whenever ChatGPT/Codex finishes work on a Moodle plugin.

The purpose of this directory is to contain deterministic Python post-processing steps that are executed against the finished plugin. The scripts may rewrite source files only when the rewrite is deterministic and safe. Canonical project validators remain authoritative and must all pass before the plugin is considered finished.

## Required execution

Before considering a plugin finished:

1. Read this file.
2. Run the post-processing runner against the plugin root:

   ```bash
   python3 .chatgpt/run.py /absolute/path/to/plugin
   ```

3. If any step returns a non-zero exit code, fix the reported problem and run the command again.
4. Do not report the plugin as finished until the runner exits with code 0.
5. After a finishing step changes files, review the diff before committing or packaging the plugin.

For validation-only execution, use:

```bash
python3 .chatgpt/run.py --check /absolute/path/to/plugin
```

## Finishing pipeline

### 1. Normalize version.php

The script `.chatgpt/scripts/normalize_version.py` enforces this project convention:

- `$plugin->release` is the first `$plugin` property assignment;
- `$plugin->version` is the second `$plugin` property assignment;
- `$plugin->supported` is not allowed; a simple assignment such as `$plugin->supported = [405, 505];` is removed automatically;
- `$plugin->requires`, when present, must be a scalar numeric Moodle build version and never an array;
- existing release/version assignments are reordered automatically when safe;
- missing metadata or unsafe/ambiguous syntax causes the finishing step to fail instead of guessing.

### 2. Normalize language files

The script `.chatgpt/scripts/normalize_lang.py` processes every PHP language file directly under:

- `lang/en/`
- `lang/pt_br/`

For every supported `$string` assignment it must:

- order language string keys alphabetically;
- use single quotes around the `$string` key;
- use single quotes around the language value;
- preserve comments located between string assignments by moving them together with the following assignment;
- preserve the file header and trailing content;
- fail instead of guessing when it finds an unsupported `$string` assignment;
- fail on duplicate language keys;
- be idempotent: running it twice must not produce a second change.

The Moodle locale directory is `pt_br`, with underscore. A `lang/pt-br` directory is considered an error.

### 3. Run every project validator

The script `.chatgpt/scripts/validate_all.py` is the Python execution layer for the complete validator suite.

It deliberately does not duplicate the PHP validation rules. Instead, it reads `src/Validator.php` to discover the currently registered validators and executes the canonical JSON CLI in `bin/moodle-string-validate`. This keeps one source of truth: adding a new rule to `Validator.php` automatically includes that rule in the finishing workflow.

At the time this instruction was written, the registered validation areas include:

- repository files;
- version and component metadata;
- plugin name;
- `install.xml` header and schema rules;
- activity module course-content API;
- activity module feature support;
- activity module backup and restore;
- database references;
- external APIs;
- subplugins;
- capabilities;
- message providers;
- cache definitions;
- Privacy API strings;
- `get_string()` references;
- translation placeholders;
- Moodle exceptions;
- legacy AJAX;
- large HTML fragments in JavaScript;
- Mustache URLs.

The Python validator runner:

- executes all validators registered in `src/Validator.php`;
- validates `en` and also `pt_br` when that locale exists;
- preserves warnings as warnings;
- returns exit code 1 when validation errors exist;
- returns exit code 2 for runtime/configuration failures;
- prints the validator explanation and suggested correction when available;
- never rewrites source code during validation.

PHP CLI must be available because the project's PHP validator remains the canonical implementation.

## Adding future finishing steps

Put each new deterministic Python step in `.chatgpt/scripts/` and add it to the ordered `STEPS` list in `.chatgpt/run.py`.

A finishing step should:

- accept the plugin root as its positional argument;
- support `--check` whenever it can rewrite files;
- return 0 when the plugin is valid;
- return a non-zero status when it cannot safely finish its job;
- print exactly what it changed or what must be corrected;
- never silently discard source code, comments, or metadata.

When adding a new canonical validator to `src/Validator.php`, do not create a second Python copy of the same rule. `validate_all.py` will execute it automatically.
