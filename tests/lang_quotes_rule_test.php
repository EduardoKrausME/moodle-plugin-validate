<?php

declare(strict_types=1);

require dirname(__DIR__) . "/autoload.php";

use EduardoKraus\MoodleStringValidate\Rule\LangQuotesRule;
use EduardoKraus\MoodleStringValidate\Validator;

function createLanguageQuoteFixture(string $english, string $portuguese): string {
    $root = sys_get_temp_dir() . "/moodle-lang-quotes-" . bin2hex(random_bytes(6));
    mkdir($root . "/lang/en", 0777, true);
    mkdir($root . "/lang/pt_br", 0777, true);
    file_put_contents($root . "/version.php", "<?php\n\$plugin->component = 'local_example';\n\$plugin->version = 2026102100;\n");
    file_put_contents($root . "/lang/en/local_example.php", $english);
    file_put_contents($root . "/lang/pt_br/local_example.php", $portuguese);
    return $root;
}

function removeLanguageQuoteFixture(string $root): void {
    foreach (glob($root . "/lang/*/*.php") ?: [] as $file) {
        unlink($file);
    }
    foreach (glob($root . "/lang/*", GLOB_ONLYDIR) ?: [] as $dir) {
        rmdir($dir);
    }
    rmdir($root . "/lang");
    unlink($root . "/version.php");
    rmdir($root);
}

function requireLanguageQuoteChecks(array $checks, int $errors, int $ok): void {
    $actualerrors = array_values(array_filter($checks, static fn($check): bool => !$check->ok));
    $actualok = array_values(array_filter($checks, static fn($check): bool => $check->ok));
    if (count($actualerrors) !== $errors || count($actualok) !== $ok) {
        fwrite(STDERR, "Unexpected langquotes results: " . json_encode($checks) . "\n");
        exit(1);
    }
}

$validator = new Validator([new LangQuotesRule()]);

$root = createLanguageQuoteFixture(<<<'LANG'
<?php
$string['pluginname'] = 'John\'s Moodle {$a}';
$string['quoteinside'] = 'He said "yes"';
$string['path'] = 'C:\\folder';
// $string["commented"] = "ignored";
$sample = '$string["example"] = "ignored";';
LANG, <<<'LANG'
<?php
$string['pluginname'] = 'Exemplo {$a}';
LANG);
$checks = $validator->validateDetailed($root);
requireLanguageQuoteChecks($checks, 0, 2);
removeLanguageQuoteFixture($root);

$root = createLanguageQuoteFixture(<<<'LANG'
<?php
$string["doubleboth"] = "Not allowed";
$string['doublevalue'] = "Not allowed";
$string["doublekey"] = 'Not allowed';
$string['concat'] = 'not' . 'allowed';
$string['dynamic'] = $dynamic;
$string['valid'] = 'Allowed';
LANG, <<<'LANG'
<?php
$string['good'] = 'Permitido';
$string["badpt"] = 'Rejeitado';
LANG);
$checks = $validator->validateDetailed($root);
requireLanguageQuoteChecks($checks, 6, 0);
$errors = array_values(array_filter($checks, static fn($check): bool => !$check->ok));
$expected = ["doubleboth", "doublevalue", "doublekey", "concat", "dynamic", "badpt"];
foreach ($errors as $index => $check) {
    if ($check->key !== $expected[$index] || $check->rule !== "langquotes" || $check->line <= 1) {
        fwrite(STDERR, "Wrong error location or key: {$check->file}:{$check->line} {$check->key}\n");
        exit(1);
    }
    $locale = $index === 5 ? "pt_br" : "en";
    if (!str_starts_with($check->file, "lang/{$locale}/")) {
        fwrite(STDERR, "Wrong language file: {$check->file}\n");
        exit(1);
    }
}
if (count($validator->validate($root)) !== 6) {
    fwrite(STDERR, "Expected six canonical validator issues.\n");
    exit(1);
}
removeLanguageQuoteFixture($root);

echo "Language quote style validation tests passed.\n";
