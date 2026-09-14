<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use EduardoKraus\MoodleStringValidate\Validator;

function createModLanguagePlugin(string $language): string {
    $root = sys_get_temp_dir() . '/moodle-string-validate-mod-' . bin2hex(random_bytes(6));
    mkdir($root . '/lang/en', 0777, true);

    file_put_contents($root . '/version.php', <<<'PHPFILE'
<?php
$plugin->component = 'mod_example';
$plugin->version = 2026081600;
PHPFILE);

    file_put_contents($root . '/lang/en/example.php', $language);
    file_put_contents($root . '/LICENSE', "GNU GPL v3 or later\n");
    file_put_contents($root . '/README.md', "# Example activity\n");

    return $root;
}

function removeModLanguagePlugin(string $root): void {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($root);
}

function findPluginAdministrationCheck(array $checks): ?object {
    foreach ($checks as $check) {
        if ($check->rule === 'pluginname' && $check->key === 'pluginadministration') {
            return $check;
        }
    }
    return null;
}

$validator = new Validator();

// mod_* must define pluginadministration in lang/en/<plugin>.php.
$root = createModLanguagePlugin(<<<'PHPFILE'
<?php
$string['pluginname'] = 'Example activity';
PHPFILE);

$checks = $validator->validateDetailed($root);
$check = findPluginAdministrationCheck($checks);
removeModLanguagePlugin($root);

if ($check === null || $check->ok) {
    fwrite(STDERR, "Expected missing pluginadministration to fail for mod_*.\n");
    exit(1);
}

// A complete activity language file passes.
$root = createModLanguagePlugin(<<<'PHPFILE'
<?php
$string['pluginname'] = 'Example activity';
$string['pluginadministration'] = 'Example administration';
PHPFILE);

$checks = $validator->validateDetailed($root);
$errors = array_values(array_filter($checks, static fn($check): bool => !$check->ok));
removeModLanguagePlugin($root);

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "[{$error->rule}] {$error->message}\n");
    }
    exit(1);
}

echo "mod_* language filename and pluginadministration test passed.\n";
