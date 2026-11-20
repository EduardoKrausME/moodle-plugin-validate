# Changelog

This changelog records verified changes in published GitHub releases. Comparisons use the immediately preceding published release. For an initial published release without a predecessor, the entry describes information checked in the tagged source.

## 1.2.8 - 2026-10-29

- Added a CSS validation rule that rejects a plugin-root `style.css` filename and redundant explicit loading of Moodle's automatically discovered root `styles.css`.
- Added a language quotation rule requiring single-quoted literal Moodle language strings.
- Honored the granular ignore option for Mustache URL validation.
- Updated normalization of `version.php` assignment ordering, developer instructions and regression tests for the new rules.

**Comparison:** [1.2.7...1.2.8](https://github.com/EduardoKrausME/moodle-plugin-validate/compare/1.2.7...1.2.8)
