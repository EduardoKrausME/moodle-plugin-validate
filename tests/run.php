<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use EduardoKraus\MoodleStringValidate\Validator;

function createPlugin(array $options = []): string {
    $root = sys_get_temp_dir() . '/moodle-string-validate-' . bin2hex(random_bytes(6));
    mkdir($root . '/db', 0777, true);
    mkdir($root . '/lang/en', 0777, true);

    file_put_contents($root . '/version.php', "<?php\n\$plugin->component = 'local_example';\n");
    file_put_contents($root . '/lang/en/local_example.php', $options['lang'] ?? <<<'PHPFILE'
<?php
$string['pluginname'] = 'Example';
PHPFILE);

    foreach (['access', 'messages', 'caches'] as $file) {
        if (isset($options[$file])) {
            file_put_contents($root . '/db/' . $file . '.php', $options[$file]);
        }
    }
    if (isset($options['subplugins'])) {
        file_put_contents($root . '/db/subplugins.json', $options['subplugins']);
    }

    return $root;
}

function removeTree(string $directory): void {
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
}

function assertKeys(array $issues, array $expected): void {
    $actual = array_map(static fn($issue) => $issue->key, $issues);
    sort($actual);
    sort($expected);
    if ($actual !== $expected) {
        fwrite(STDERR, "Expected:\n" . print_r($expected, true) . "Actual:\n" . print_r($actual, true));
        exit(1);
    }
}

function assertCheckStatuses(array $checks, array $expected): void {
    $actual = [];
    foreach ($checks as $check) {
        $actual[$check->key] = $check->ok ? 'OK' : 'ERROR';
    }
    ksort($actual);
    ksort($expected);
    if ($actual !== $expected) {
        fwrite(STDERR, "Expected statuses:\n" . print_r($expected, true) . "Actual statuses:\n" . print_r($actual, true));
        exit(1);
    }
}

$validator = new Validator();

$root = createPlugin([
    'access' => <<<'PHPFILE'
<?php
$capabilities = [
    'local/example:manage' => [
        'captype' => 'write',
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
];
PHPFILE,
    'messages' => <<<'PHPFILE'
<?php
$messageproviders = [
    'notice' => [],
];
PHPFILE,
    'caches' => <<<'PHPFILE'
<?php
$definitions = [
    'result' => [
        'mode' => cache_store::MODE_APPLICATION,
    ],
];
PHPFILE,
    'subplugins' => json_encode([
        'plugintypes' => ['exampletype' => 'local/example/exampletype'],
        'subplugintypes' => ['exampletype' => 'exampletype'],
    ], JSON_PRETTY_PRINT),
]);
assertKeys($validator->validate($root), [
    'example:manage',
    'messageprovider:notice',
    'cachedef_result',
    'subplugintype_exampletype',
    'subplugintype_exampletype_plural',
]);
assertCheckStatuses($validator->validateDetailed($root), [
    'pluginname' => 'OK',
    'example:manage' => 'ERROR',
    'messageprovider:notice' => 'ERROR',
    'cachedef_result' => 'ERROR',
    'subplugintype_exampletype' => 'ERROR',
    'subplugintype_exampletype_plural' => 'ERROR',
]);
removeTree($root);

$root = createPlugin([
    'access' => "<?php\n\$capabilities = ['local/example:manage' => []];\n",
    'messages' => "<?php\n\$messageproviders = ['notice' => []];\n",
    'caches' => "<?php\n\$definitions = ['result' => []];\n",
    'subplugins' => '{"plugintypes":{"exampletype":"local/example/exampletype"}}',
    'lang' => <<<'PHPFILE'
<?php
$string['pluginname'] = 'Example';
$string['example:manage'] = 'Manage';
$string['messageprovider:notice'] = 'Notice';
$string['cachedef_result'] = 'Result cache';
$string['subplugintype_exampletype'] = 'Example type';
$string['subplugintype_exampletype_plural'] = 'Example types';
PHPFILE,
]);
assertKeys($validator->validate($root), []);
assertCheckStatuses($validator->validateDetailed($root), [
    'pluginname' => 'OK',
    'example:manage' => 'OK',
    'messageprovider:notice' => 'OK',
    'cachedef_result' => 'OK',
    'subplugintype_exampletype' => 'OK',
    'subplugintype_exampletype_plural' => 'OK',
]);
removeTree($root);

$root = createPlugin([
    'lang' => <<<'PHPFILE'
<?php
$string['pluginname'] = '';
PHPFILE,
]);
assertKeys($validator->validate($root), ['pluginname']);
assertKeys($validator->validate($root, 'en', false), []);
assertCheckStatuses($validator->validateDetailed($root), ['pluginname' => 'ERROR']);
assertCheckStatuses($validator->validateDetailed($root, 'en', false), ['pluginname' => 'OK']);
removeTree($root);

echo "All tests passed.\n";
