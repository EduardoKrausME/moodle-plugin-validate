# Moodle String Validate

Static PHP validator for Moodle plugin structure, metadata, subplugins, and language strings. It does not bootstrap or install Moodle, so it can run before the heavier `moodle-plugin-ci` installation step.

Every executed validation is printed. Successful checks appear as `OK`; invalid configuration appears as `ERROR` and makes the command return exit code `1`. Advisory architecture checks appear as `WARNING` and do not fail CI.

## Validations

### Project root

The validator checks that the plugin repository contains:

- a license file in the project root: `LICENSE`, `LICENSE.md`, `LICENSE.txt`, `COPYING`, or `COPYING.txt`;
- a README in the project root: `README.md`, `README`, `README.txt`, or `README.rst`;
- `version.php` in the plugin root;
- a valid `$plugin->component`;
- a positive numeric `$plugin->version`.

### Language strings

The base language file is validated against Moodle metadata:

| Source | Required language string |
| --- | --- |
| Plugin | `pluginname` |
| `db/subplugins.json` | `subplugintype_<type>` and `subplugintype_<type>_plural` |
| `db/access.php` | `<pluginname>:<capability>` |
| `db/messages.php` | `messageprovider:<provider>` |
| `db/caches.php` | `cachedef_<definition>` |
| `classes/privacy/provider.php` | every literal `privacy:*` key referenced by the provider |
| PHP source | literal `get_string(<key>, <current component>)` calls |

Missing strings fail validation. Required strings with an empty value also fail by default.

`get_string()` validation intentionally checks only calls where both the key and component are literal strings and the component is the plugin currently being validated. One-argument core calls such as `get_string('savechanges')`, calls for other components, and dynamically generated keys are ignored.

Privacy validation reads `classes/privacy/provider.php` statically and validates every unique literal string beginning with `privacy:` against the base language file.

### `db/subplugins.json`

`db/subplugins.json` is optional for a normal Moodle plugin. If it is absent and no nested Moodle plugins are found, that check is reported as `OK`.

If nested Moodle plugins are detected in the repository but `db/subplugins.json` is missing, validation fails.

The canonical file name is exactly:

```text
db/subplugins.json
```

Names such as `db/sub-plugins.json`, `db/sub_plugins.json`, or a root-level `subplugins.json` are reported as errors.

When `db/subplugins.json` exists, the validator checks:

- valid JSON;
- top-level tags are `plugintypes` and/or `subplugintypes`;
- declared tags are non-empty JSON objects;
- no unknown top-level tags are present;
- subplugin type names contain only lowercase letters, numbers, and underscores;
- paths are valid relative paths and do not contain `..`, leading `/`, trailing `/`, or backslashes;
- when both `plugintypes` and `subplugintypes` are declared, both contain the same type keys;
- when both formats are declared, their paths refer to the same location;
- the local directory declared for each subplugin type exists;
- both required language strings exist for every declared subplugin type.

For plugin types whose Moodle root path can be derived directly, the legacy `plugintypes` path is checked exactly against the modern `subplugintypes` path. This currently includes `local`, `mod`, `tool`, and `editor` parent plugins.

Example:

```json
{
  "plugintypes": {
    "recerttask": "local/kopere_recert/recerttask"
  },
  "subplugintypes": {
    "recerttask": "recerttask"
  }
}
```

### Bundled subplugins

Every immediate subdirectory inside a declared subplugin type directory is treated as a bundled subplugin and checked independently.

For example:

```text
recerttask/
├── activitycompletion/
│   └── version.php
├── competency/
│   └── version.php
└── forum/
    └── version.php
```

For `recerttask/activitycompletion`, the expected component is:

```php
$plugin->component = 'recerttask_activitycompletion';
```

The validator checks for every bundled subplugin:

- `version.php` exists;
- `$plugin->component` is exactly `<subplugintype>_<directoryname>`;
- `$plugin->version` is numeric and greater than zero;
- `$plugin->dependencies` explicitly contains the parent plugin component;
- the minimum parent version required by the subplugin is not newer than the parent version bundled in the same repository.

A subplugin may require an older parent version. For example, this is valid when the current parent version is `2026081302`:

```php
$plugin->dependencies = [
    'local_kopere_recert' => 2026081204,
];
```

`ANY_VERSION` is also accepted as a valid explicit dependency.

## Advisory warnings

Warnings are reported but do not change the successful exit code when there are no errors.

### Legacy AJAX

The validator warns when it finds a PHP endpoint declaring `AJAX_SCRIPT` directly or a jQuery `$.ajax()` call pointing to a PHP endpoint. PHP handlers using `$_FILES` are ignored because multipart upload endpoints may legitimately need a dedicated handler.

### Large HTML fragments in JavaScript

The validator warns when `innerHTML`, jQuery `.html()`, or `insertAdjacentHTML()` receives a literal HTML fragment of at least 200 bytes. The warning suggests moving substantial markup to a Mustache template. Minified JavaScript and `amd/build` output are ignored.

## Static analysis

PHP metadata files are inspected without executing them. The validator does not `require` `access.php`, `messages.php`, `caches.php`, or subplugin `version.php` files.

That means Moodle constants and classes such as these do not need to exist in the validation environment:

```php
CAP_ALLOW
CONTEXT_COURSE
RISK_CONFIG
cache_store::MODE_APPLICATION
```

## Command line

```bash
php bin/moodle-string-validate /path/to/plugin
```

For GitHub annotations:

```bash
php bin/moodle-string-validate /path/to/plugin --format=github
```

Options:

```text
--lang=en       Language directory to validate. Default: en
--allow-empty   Do not fail when a required string exists but is empty
--format=text   Human-readable output. Default
--format=github GitHub Actions annotations
```

Exit codes:

```text
0  No validation errors were found (warnings are allowed)
1  One or more validation errors were found
2  Invalid arguments or runtime error
```

## GitHub Action

```yaml
- name: Validate Moodle plugin
  uses: EduardoKrausME/moodle-string-validate@main
  with:
    plugin: ./plugin
```

After creating a stable `v1` tag, consumers can use:

```yaml
- name: Validate Moodle plugin
  uses: EduardoKrausME/moodle-string-validate@v1
  with:
    plugin: ./plugin
```

For a workflow using `moodle-plugin-ci`, place this validation after PHP setup and before `moodle-plugin-ci install` so metadata problems fail quickly.

## Output example

```text
Moodle String Validate
======================

OK [repository] license file exists in project root: LICENSE.
OK [repository] README file exists in project root: README.md.
OK [version] version.php exists in project root.
OK [version] Plugin component is correctly configured as 'local_kopere_recert'.
OK [version] Plugin version is configured: 2026081302.
OK [pluginname] $string['pluginname']
OK [subplugin] db/subplugins.json exists and contains valid JSON.
OK [subplugin] The 'plugintypes' tag is correctly configured as a non-empty JSON object.
OK [subplugin] The 'subplugintypes' tag is correctly configured as a non-empty JSON object.
OK [subplugin] The 'plugintypes' and 'subplugintypes' tags contain the same type keys.
OK [subplugin] Subplugin type 'recerttask' has matching legacy and modern paths.
OK [subplugin] $string['subplugintype_recerttask']
OK [subplugin] $string['subplugintype_recerttask_plural']
OK [subplugin] Subplugin 'activitycompletion' correctly identifies itself as 'recerttask_activitycompletion'.
OK [subplugin] Subplugin 'recerttask_activitycompletion' declares dependency on parent 'local_kopere_recert'.
OK [subplugin] Subplugin 'recerttask_activitycompletion' parent dependency version 2026081204 is compatible with bundled parent version 2026081302.

15 OK, 0 warnings, 0 errors.
```

## Local development

The runtime requirement is PHP 8.1 or newer. There are no required Composer dependencies.

```bash
php tests/run.php
```

Composer is optional. The project includes PSR-4 metadata and a Composer `bin` entry for installations that prefer using it as a development dependency.
