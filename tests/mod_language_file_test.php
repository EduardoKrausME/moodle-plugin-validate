<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use EduardoKraus\MoodleStringValidate\Validator;

$root = sys_get_temp_dir() . '/moodle-string-validate-mod-' . bin2hex(random_bytes(6));
mkdir($root . '/lang/en', 0777, true);

file_put_contents($root . '/version.php', <<<'PHPFILE'
<?php
$plugin->component = 'mod_example';
$plugin->version = 2026081600;
PHPFILE);

file_put_contents($root . '/lang/en/example.php', <<<'PHPFILE'
<?php
$string['pluginname'] = 'Example activity';
PHPFILE);

file_put_contents($root . '/LICENSE', "GNU GPL v3 or later\n");
file_put_contents($root . '/README.md', "# Example activity\n");

$validator = new Validator();
$checks = $validator->validateDetailed($root);
$errors = array_values(array_filter($checks, static fn($check): bool => !$check->ok));

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($iterator as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}
rmdir($root);

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "[{$error->rule}] {$error->message}\n");
    }
    exit(1);
}

echo "mod_* language filename test passed.\n";
