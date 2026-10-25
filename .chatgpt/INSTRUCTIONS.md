# Moodle plugin finishing instructions

These instructions are mandatory whenever ChatGPT/Codex finishes work on a Moodle plugin.

The purpose of this directory is to contain deterministic Python post-processing steps that are executed against the finished plugin. The scripts may rewrite source files only when the rewrite is deterministic and safe. Canonical project validators remain authoritative and must all pass before the plugin is considered finished.

## Mandatory Git and Moodle development conventions

These requirements apply to **all Moodle plugins created, maintained, refactored or fixed** through this workflow. Follow them before editing, while implementing changes and before committing. They take precedence over older examples, formatter behavior or conventions in this document when those conflict. A tooling mismatch is not permission to ignore a mandatory requirement; report it explicitly and resolve it in an authorized change.

1. **Git: `master` only.** Make all repository changes directly on the existing `master` branch. Do not create auxiliary branches, feature branches, tags or GitHub Releases. **Never create tags**, including for version bumps. Do not open pull requests as a substitute for direct `master` changes.
2. **`version.php`: altere exclusivamente `$plugin->version`; proteja `$plugin->release` e exija autorização para commits de versão.** Em qualquer `version.php` (raiz ou subplugin), a **única atribuição que pode ser modificada é `$plugin->version`**, quando uma atualização de versão for solicitada ou necessária e permitida pela tarefa. **Nunca altere `$plugin->release`**, mesmo que `$plugin->version` mude. Preserve também todos os demais campos, comentários, formatação e a ordem das atribuições em `version.php`; não reordene linhas nem aplique normalizações que produzam outras alterações nesse arquivo. **Jamais crie um commit com mensagem no formato `V N.N.N` sem autorização prévia, específica e explícita do usuário para essa mensagem e versão**, ainda que o commit altere `/version.php` na raiz. Uma autorização genérica para corrigir o plugin, atualizar `$plugin->version` ou realizar commits **não** autoriza `V N.N.N`. Sem essa autorização específica, use uma mensagem de commit descritiva comum, inclusive para mudanças de `$plugin->version` na raiz ou em subplugins. A mensagem `V N.N.N` pode acionar tags/releases automáticos e nunca deve ser usada por convenção ou inferência. Não crie tags ou releases e não altere versões silenciosamente.
3. **Protect GitHub Actions workflows.** Never create, edit, move, rename or delete anything under `.github/workflows/` (including nested paths), even to fix a failing build. Report required workflow changes instead of applying them.
4. **Mustache-only UI; no renderers.** Use Mustache templates for interface output. Do not create `renderer.php` files, custom renderer classes, or `export_for_template()` methods. Prepare template data in appropriate service, page or presentation classes without implementing renderer subclasses.
5. **English as source language; Brazilian Portuguese translations.** Keep the canonical language pack under `lang/en/` and provide Brazilian Portuguese translations under `lang/pt_br/` (underscore, not `pt-br`). Never hardcode Portuguese user-facing text in PHP classes, page controllers, JavaScript or Mustache templates. Reference Moodle language strings via the native language APIs, keeping translation placeholders consistent.
6. **Moodle compatibility.** Support Moodle **4.5 LTS as the minimum** and preserve compatibility with Moodle **5.x**. Prefer native public Moodle APIs, established extension points and feature-detection guards when an API differs between supported versions. Do not introduce dependencies on APIs that exist only in newer Moodle versions without a compatible fallback.
7. **Subplugin registration.** Whenever a plugin exposes subplugin types, provide `db/subplugins.json` containing **both** `subplugintypes` and `plugintypes` entries for wider compatibility across Moodle versions and recent validation warnings, and provide an `admin_plugins.php` file for administration integration. Keep type names and paths consistent with the actual subplugins and verify against Moodle 4.5 and 5.x.
8. **English identifiers and comments.** Write code comments, function names, method names, variable names and class names in English. Follow Moodle naming standards and preserve public API compatibility when refactoring existing identifiers.
9. **Quote style by file type.** Use **double quotes** for quoted strings in new or modified PHP and JavaScript code **outside Moodle language files**, unless a syntax constraint makes this impossible. **Mandatory exception for every `lang/<locale>/*.php` file:** each language assignment must use **single quotes for both the key and the value**, with the canonical form `$string['key'] = 'value';`. Never write `$string["key"] = "value";`, mix single and double quotes, or use a non-literal language value. Escape apostrophes and backslashes correctly to preserve PHP string semantics and Moodle placeholders. The `LangQuotesRule` validator rejects violations, and `.chatgpt/scripts/normalize_lang.py` normalizes supported one-line assignments.
10. **Keep `lib.php` minimal.** Use `lib.php` only for global Moodle callbacks or entry points that Moodle requires in that file. Put business logic, persistence, service orchestration, calculations and reusable functionality in appropriately namespaced classes.
11. **Prefer cohesive, focused classes.** Separate responsibilities into classes as much as practical: controllers or page orchestration, data access, domain services, reporting, forms, and template-data preparation should not accumulate as unrelated global functions. Keep classes focused and avoid replacing procedural sprawl with a single oversized class.
12. **Vanilla JavaScript inside AMD modules.** When JavaScript is required, use native JavaScript for application behavior and the Moodle-compatible AMD module pattern `define(["jquery"], function ($) { ... });`. The AMD dependency wrapper does not authorize writing application logic with jQuery; prefer native DOM and browser APIs. Provide translatable JS strings using `$PAGE->requires->strings_for_js()` on the PHP side instead of embedding Portuguese or other fixed translated text.
13. **Render Mustache through Moodle.** Render all plugin-owned interface templates with `$OUTPUT->render_from_template("component/template", $data)`. Avoid manually concatenating HTML and bypassing Moodle's Mustache rendering pipeline.

14. **PHPUnit `@covers` metadata is mandatory.** Every PHPUnit test method (including methods named `test_*` and methods otherwise recognized by PHPUnit as tests) must have meaningful coverage metadata via a PHPDoc `@covers` annotation. Use `@covers \fully\qualified\ClassName` in the **test class PHPDoc** when all its test methods exercise the same production class; the class-level annotation covers every test method in that class. When test methods target different production classes or methods, add the applicable `@covers \fully\qualified\ClassName::methodName` annotations to each **test method PHPDoc**, with multiple `@covers` entries when needed. Reference the actual code under test, never the test class itself. Do not use `@coversNothing`, PHPCS suppressions, or dummy annotations to bypass `moodle.PHPUnit.TestCaseCovers.Missing`. Before committing changes to PHPUnit tests, run `moodle-plugin-ci phpcs` when available, correct all coverage warnings, and report explicitly if that check cannot be run.

Before every commit, review the final diff and confirm: the target is `master`; no `.github/workflows/` files were changed; no branch, tag or release was created; **if any `version.php` was changed, only the `$plugin->version` assignment changed, while `$plugin->release` and every other byte remain untouched**; **no commit message matches `V N.N.N` without the user's prior, specific, explicit authorization for that exact versioned commit**; and the implementation follows the language, template, class, PHPUnit coverage and compatibility conventions above.

## Repository access and restricted-network environments

The finishing pipeline needs a **complete local checkout of this validator repository**, not only a copy of `.chatgpt/`. In particular, `.chatgpt/scripts/validate_all.py` reads `src/Validator.php` and runs `bin/moodle-string-validate` from the validator repository. Python 3 and PHP CLI must be available.

Do not assume that a ChatGPT/Codex execution environment can reach GitHub with `git clone`. The message `Could not resolve host: github.com` indicates a DNS or network restriction in the execution environment, not a defect in the plugin or a validation failure. Changing HTTPS to SSH or repeating the clone does not fix unavailable DNS.

Use this acquisition and validation order:

1. **Reuse a complete local validator checkout** if one is already mounted or cached. Check that `.chatgpt/run.py`, `src/Validator.php` and `bin/moodle-string-validate` exist in that checkout.
2. If there is no local checkout and the environment allows GitHub access, obtain the full validator repository **once**, outside the Moodle plugin root.
3. If GitHub DNS/network access is blocked, **do not keep retrying `git clone`**. A connected GitHub integration may be used to inspect and edit GitHub files, but API access alone does not create a local executable checkout. Use an available workspace mount, an existing complete artifact, or another supported transfer mechanism to stage the **entire** validator repository locally before invoking the runner.
4. If the complete validator cannot be made available locally, do not invent passing results or substitute superficial checks for the full suite. Report **local validation blocked by environment**, including the exact missing prerequisite, and use GitHub Actions as an alternate execution environment when available. A successful remote run may be reported as a CI validation, never as an executed local test.
5. **Use `--check` by default.** The default runner can normalize files, including `version.php`, and may change `$plugin->release`, assignment order or other protected content. **Never run a mutating normalization mode that would modify anything in `version.php` except the authorized `$plugin->version` assignment**, even when a version bump is allowed. Apply the permitted `$plugin->version` edit directly, review its exact diff, and report or skip an incompatible normalization step instead of allowing it to rewrite protected lines.

Example using an existing local checkout (no network call):

```bash
python3 /path/to/moodle-plugin-validate/.chatgpt/run.py --check /absolute/path/to/plugin
```

For repositories that do not have a local validator checkout, a GitHub Actions job can obtain both repositories **inside GitHub's runner**, instead of attempting to clone them in a network-restricted ChatGPT/Codex container:

```yaml
jobs:
  validate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          path: plugin
      - uses: actions/checkout@v4
        with:
          repository: EduardoKrausME/moodle-plugin-validate
          ref: master
          path: validator
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - run: python3 validator/.chatgpt/run.py --check "$GITHUB_WORKSPACE/plugin"
```

The CI example is validation-only. It does not commit automatic corrections; apply permitted formatting changes to the plugin source and rerun the checks.

## Required execution

Before considering a plugin finished:

1. Read this file.
2. Run the post-processing runner against the plugin root in **non-mutating check mode**, ensuring `version.php` is never implicitly rewritten:

   ```bash
   python3 .chatgpt/run.py --check /absolute/path/to/plugin
   ```

3. If any step returns a non-zero exit code, fix the reported problem and run the command again.
4. Do not report the plugin as finished until the runner exits with code 0.
5. After a finishing step changes files, review the diff before committing or packaging the plugin.

For validation-only execution, use:

```bash
python3 .chatgpt/run.py --check /absolute/path/to/plugin
```

## Finishing pipeline

### 1. Normalize PHP headers

The script `.chatgpt/scripts/normalize_php_headers.py` is a formatter, not a validator rule.

It processes every first-party `.php` file in the plugin and guarantees the complete Moodle file header:

- the full GPL boilerplate begins immediately after `<?php`;
- a separate file PHPDoc block follows the GPL text;
- the PHPDoc contains a short file description, `@package`, `@copyright` and `@license`;
- `@package` is discovered from the closest `version.php`, so bundled subplugins receive their own Frankenstyle component;
- existing copyrights are preserved; when a file has no copyright, the formatter reuses the plugin's Eduardo Kraus copyright or creates `<year> Eduardo Kraus {@link https://eduardokraus.com}`;
- a partial or malformed leading Moodle header is removed and replaced with the complete canonical block;
- executable PHP below the header is preserved;
- `vendor`, `node_modules`, `thirdparty`, `third_party` and repository/editor metadata directories are not rewritten.

This convention is intentionally implemented only as post-processing. It must not create a PHP validator rule merely to enforce formatting.

### 2. Normalize version.php

The script `.chatgpt/scripts/normalize_version.py` is a maintenance formatter, not a validator rule.

It rewrites `version.php` so the two values normally changed during a release are always at the top:

- `$plugin->version` is the first `$plugin` assignment;
- `$plugin->release` is the second `$plugin` assignment;
- all other `$plugin` assignments keep their existing relative order;
- a simple `$plugin->supported = [...];` line is removed as part of the cleanup convention;
- it does not add any equivalent rule to `VersionRule.php` and does not make this layout part of plugin validation.

**Mandatory safety restriction:** the behavior described above is **not authorization** to run this formatter in mutating mode. As long as it can reorder `$plugin->release` or change any other part of `version.php` besides an explicitly permitted `$plugin->version` assignment, **do not execute it in mutating mode on the plugin**. Keep `$plugin->release` and every other line exactly as they were; edit only `$plugin->version` directly when authorized. A successful validation does not override this protection.

### 3. Normalize language files

The script `.chatgpt/scripts/normalize_lang.py` processes every PHP language file directly under:

- `lang/en/`
- `lang/pt_br/`

For every supported `$string` assignment it must:

- order language string keys alphabetically;
- use single quotes around the `$string` key (required by rule 9);
- use single quotes around the language value (required by rule 9);
- preserve comments located between string assignments by moving them together with the following assignment;
- preserve the file header and trailing content;
- fail instead of guessing when it finds an unsupported `$string` assignment;
- fail on duplicate language keys;
- be idempotent: running it twice must not produce a second change.

The Moodle locale directory is `pt_br`, with underscore. A `lang/pt-br` directory is considered an error.

### 4. Run every project validator

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
- single-quoted keys and values in all `lang/<locale>/*.php` files (including additional locales);
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
