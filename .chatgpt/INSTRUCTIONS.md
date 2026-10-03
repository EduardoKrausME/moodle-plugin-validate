# Moodle plugin finishing instructions

These instructions are mandatory whenever ChatGPT/Codex finishes work on a Moodle plugin.

The purpose of this directory is to contain deterministic Python post-processing steps that are executed against the finished plugin. The scripts are allowed to rewrite source files when the rewrite is deterministic and safe.

## Required execution

Before considering a plugin finished:

1. Read this file.
2. Run the post-processing runner against the plugin root:

   ```bash
   python3 .chatgpt/run.py /absolute/path/to/plugin
   ```

3. If any step returns a non-zero exit code, fix the reported problem and run the command again.
4. Do not report the plugin as finished until the runner exits with code 0.
5. After the runner changes files, review the diff before committing or packaging the plugin.

For CI or validation-only execution, use:

```bash
python3 .chatgpt/run.py --check /absolute/path/to/plugin
```

## Current finishing steps

### Language files

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

## Adding future finishing steps

Put each new deterministic Python step in `.chatgpt/scripts/` and add it to the ordered `STEPS` list in `.chatgpt/run.py`.

A finishing step should:

- accept the plugin root as its positional argument;
- support `--check` whenever it can rewrite files;
- return 0 when the plugin is valid;
- return a non-zero status when it cannot safely finish its job;
- print exactly what it changed or what must be corrected;
- never silently discard source code, comments, or metadata.
