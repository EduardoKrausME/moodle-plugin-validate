# Moodle String Validate

Static PHP validator for language strings that Moodle expects from plugin metadata files. It does not bootstrap or install Moodle, so it is fast enough to run before the heavier `moodle-plugin-ci` jobs.

## Validations

The first version validates the base language file, `lang/en/<component>.php`, against these Moodle plugin files:

| Source | Required language string |
| --- | --- |
| Plugin | `pluginname` |
| `db/subplugins.json` | `subplugintype_<type>` and `subplugintype_<type>_plural` |
| `db/access.php` | `<pluginname>:<capability>` |
| `db/messages.php` | `messageprovider:<provider>` |
| `db/caches.php` | `cachedef_<definition>` |

Missing strings fail validation. Required strings with an empty value also fail by default. Every successful check is also printed as `OK`, so the CI log shows both what passed and what failed.

The PHP metadata files are inspected statically with `token_get_all()`. They are not included or executed, so constants such as `CAP_ALLOW`, `CONTEXT_COURSE`, `RISK_CONFIG`, and `cache_store::MODE_APPLICATION` do not require a Moodle installation.

## Command line

```bash
php bin/moodle-string-validate /path/to/plugin
```

For GitHub annotations:

```bash
php bin/moodle-string-validate /path/to/plugin --format=github
```

Other options:

```text
--lang=en       Language directory to validate. Default: en
--allow-empty   Do not fail when a required string exists but is empty
--format=text   Human-readable output. Default
--format=github GitHub Actions annotations
```

The command exits with `0` when validation succeeds, `1` when language errors are found, and `2` for invalid usage or runtime errors.

## GitHub Action

While developing, another repository can use `@main`. After the first stable release, create a `v1` tag and switch consumers to `@v1`:

```yaml
- name: Validate Moodle language strings
  uses: EduardoKrausME/moodle-string-validate@main
  with:
    plugin: ./plugin
```

The action prints every successful validation as `OK` and reports errors on the file and line that introduced the requirement whenever possible.

## Using it in moodle-plugin-ci

For a workflow that already checks the plugin out to `./plugin`, place the validation after PHP setup and before the expensive Moodle installation step:

```yaml
- name: Validate Moodle language strings
  uses: EduardoKrausME/moodle-string-validate@v1
  with:
    plugin: ./plugin
```

This catches metadata/string mismatches before PHPUnit or Behat start.

## Local development

No runtime dependency is required other than PHP 8.1 or newer.

```bash
php tests/run.php
```

Composer is optional. The package provides PSR-4 metadata and a Composer `bin` entry for projects that prefer installing the validator as a development dependency.


## Output example

```text
Moodle String Validate
======================

OK [pluginname] $string['pluginname']
OK [subplugin] $string['subplugintype_recerttask']
ERROR [subplugin] lang/en/local_kopere_recert.php:142
  Language string $string['subplugintype_recerttask_plural'] is empty; required by subplugin type 'recerttask' in db/subplugins.json.
OK [access] $string['kopere_recert:manage']
OK [messageprovider] $string['messageprovider:kopere_recert_available']

4 OK, 1 error.
```
