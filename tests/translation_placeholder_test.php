<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use EduardoKraus\MoodleStringValidate\Validator;

$root = sys_get_temp_dir() . '/moodle-string-validate-placeholders-' . bin2hex(random_bytes(6));
mkdir($root . '/lang/en', 0777, true);
mkdir($root . '/lang/pt_br', 0777, true);
mkdir($root . '/lang/es', 0777, true);

file_put_contents($root . '/version.php', <<<'PHPFILE'
<?php
$plugin->component = 'local_example';
$plugin->version = 2026091400;
PHPFILE);
file_put_contents($root . '/LICENSE', "GNU GPL v3 or later\n");
file_put_contents($root . '/README.md', "# Example\n");

file_put_contents($root . '/lang/en/local_example.php', <<<'PHPFILE'
<?php
$string['pluginname'] = 'Example';
$string['object'] = 'Hello {$a->name}, you have {$a->count} items.';
$string['scalar'] = 'Hello {$a}';
$string['repeat'] = '{$a->name} and {$a->name}';
$string['plain'] = 'Nothing here';
$string['fallback'] = 'ID {$a->id}';
PHPFILE);

file_put_contents($root . '/lang/pt_br/local_example.php', <<<'PHPFILE'
<?php
$string['pluginname'] = 'Exemplo';
$string['object'] = 'Olá {$a->name}, você tem {$a->count} itens.';
$string['scalar'] = 'Olá {$a}';
$string['repeat'] = '{$a->name}';
$string['plain'] = 'Nada {$a}';
PHPFILE);

file_put_contents($root . '/lang/es/local_example.php', <<<'PHPFILE'
<?php
$string['pluginname'] = 'Ejemplo';
$string['object'] = 'Hola {$a}, tienes {$a->count} elementos.';
$string['scalar'] = 'Hola {$a}';
$string['repeat'] = '{$a->name} y {$a->name}';
$string['plain'] = 'Nada';
PHPFILE);

$checks = (new Validator())->validateDetailed($root);
$placeholdererrors = array_values(array_filter(
    $checks,
    static fn($check): bool => $check->rule === 'translationplaceholder' && $check->isError(),
));

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST,
);
foreach ($iterator as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}
rmdir($root);

if (count($placeholdererrors) !== 3) {
    fwrite(STDERR, 'Expected 3 translation placeholder errors, got ' . count($placeholdererrors) . "\n");
    foreach ($placeholdererrors as $error) {
        fwrite(STDERR, "[{$error->file}:{$error->line}] {$error->message}\n");
    }
    exit(1);
}

$messages = implode("\n", array_map(static fn($error): string => $error->message, $placeholdererrors));
foreach ([
    "missing {\$a->name}; unexpected {\$a}",
    "missing {\$a->name}",
    "unexpected {\$a}",
] as $expected) {
    if (!str_contains($messages, $expected)) {
        fwrite(STDERR, "Expected placeholder mismatch not found: {$expected}\n");
        exit(1);
    }
}

echo "Translation placeholder test passed.\n";
