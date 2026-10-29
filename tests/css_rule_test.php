<?php

declare(strict_types=1);

require dirname(__DIR__) . "/autoload.php";

use EduardoKraus\MoodleStringValidate\Rule\CssRule;
use EduardoKraus\MoodleStringValidate\Validator;

function createCssFixture(): string {
    $root = sys_get_temp_dir() . "/moodle-css-rule-" . bin2hex(random_bytes(6));
    mkdir($root . "/lang/en", 0777, true);
    file_put_contents($root . "/version.php", "<?php\n\$plugin->component = 'local_example';\n\$plugin->version = 2026102900;\n");
    file_put_contents($root . "/lang/en/local_example.php", "<?php\n\$string['pluginname'] = 'Example';\n");
    return $root;
}

function removeCssFixture(string $root): void {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($root);
}

function assertCssFindings(array $checks, int $errors): void {
    $invalid = array_values(array_filter($checks, static fn($check): bool => !$check->ok));
    if (count($invalid) !== $errors) {
        fwrite(STDERR, "Expected {$errors} CSS errors, got " . json_encode($checks) . "\n");
        exit(1);
    }
    foreach ($invalid as $check) {
        if ($check->rule !== "css" || $check->line < 1 || $check->severity !== "error") {
            fwrite(STDERR, "Invalid CSS check metadata: " . json_encode($check) . "\n");
            exit(1);
        }
    }
}

$validator = new Validator([new CssRule()]);
$root = createCssFixture();

// A stylesheet is optional, and the canonical root name is valid.
assertCssFindings($validator->validateDetailed($root), 0);
file_put_contents($root . "/styles.css", ".example { color: black; }\n");
assertCssFindings($validator->validateDetailed($root), 0);

// A root-level style.css is always an error, even if styles.css also exists.
file_put_contents($root . "/style.css", ".example { color: black; }\n");
$checks = $validator->validateDetailed($root);
assertCssFindings($checks, 1);
if ($checks[0]->file !== "style.css" || $checks[0]->key !== "style.css") {
    fwrite(STDERR, "Expected an error pointing to root style.css.\n");
    exit(1);
}
unlink($root . "/style.css");

// A nested style.css is not Moodle's automatically loaded plugin stylesheet.
mkdir($root . "/css");
file_put_contents($root . "/css/style.css", ".example { color: black; }\n");
assertCssFindings($validator->validateDetailed($root), 0);

// Comments, quoted examples and nested stylesheets are ignored.
$source = <<<'PHPFILE'
<?php
// $PAGE->requires->css('/local/example/styles.css');
$example = '$PAGE->requires->css("/local/example/styles.css");';
/* $PAGE->requires->css('/local/example/styles.css'); */
$PAGE->requires->css('/local/example/styles.css');
$PAGE->requires->css(new \moodle_url("/local/example/styles.css"));
$PAGE
    ->requires
    ->css(
        $CFG->wwwroot . '/local/example/styles.css'
    );
$PAGE->requires->css('styles.css');
$PAGE->requires->css('/local/example/css/styles.css');
$PAGE->requires->css('/local/other/styles.css');
$PAGE->requires->css('/local/example/custom.css');
PHPFILE;
file_put_contents($root . "/index.php", $source);
$checks = $validator->validateDetailed($root);
assertCssFindings($checks, 5);
$errors = array_values(array_filter($checks, static fn($check): bool => !$check->ok));
if (array_column($errors, "line") !== [5, 6, 7, 12, 14]) {
    fwrite(STDERR, "Unexpected PHP source locations: " . json_encode(array_column($errors, "line")) . "\n");
    exit(1);
}
foreach ($errors as $check) {
    if ($check->file !== "index.php" || $check->key !== "styles.css") {
        fwrite(STDERR, "Wrong CSS error location or key: " . json_encode($check) . "\n");
        exit(1);
    }
}
if (count($validator->validate($root)) !== 5) {
    fwrite(STDERR, "Expected CSS errors to fail canonical validation.\n");
    exit(1);
}

// Ignore support is opt-in and suppresses both CSS checks.
assertCssFindings($validator->validateDetailed($root, "en", true, ["css"]), 0);
removeCssFixture($root);

echo "CSS filename and auto-loading validation tests passed.\n";
