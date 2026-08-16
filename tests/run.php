<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\Validator;

function createPlugin(array $options = []): string {
    $root = sys_get_temp_dir() . '/moodle-string-validate-' . bin2hex(random_bytes(6));
    mkdir($root . '/db', 0777, true);
    mkdir($root . '/lang/en', 0777, true);

    $version = $options['version'] ?? 2026081600;
    $component = $options['component'] ?? 'local_example';
    file_put_contents($root . '/version.php', $options['versionfile'] ?? "<?php\n\$plugin->component = '{$component}';\n\$plugin->version = {$version};\n");
    file_put_contents($root . '/lang/en/' . $component . '.php', $options['lang'] ?? <<<'PHPFILE'
<?php
$string['pluginname'] = 'Example';
PHPFILE);

    if (!($options['nolicense'] ?? false)) {
        file_put_contents($root . '/LICENSE', "GNU GPL v3 or later\n");
    }
    if (!($options['noreadme'] ?? false)) {
        file_put_contents($root . '/README.md', "# Example\n");
    }

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

function createSubplugin(
    string $root,
    string $type,
    string $name,
    string $parent = 'local_example',
    int $parentrequired = 2026081500,
    ?string $component = null,
    bool $dependency = true,
    int $version = 2026081600,
): void {
    $directory = $root . '/' . $type . '/' . $name;
    mkdir($directory, 0777, true);
    $component ??= $type . '_' . $name;
    $dependencyline = $dependency
        ? "\$plugin->dependencies = ['{$parent}' => {$parentrequired}];\n"
        : '';
    file_put_contents($directory . '/version.php', <<<PHPFILE
<?php
\$plugin->component = '{$component}';
\$plugin->version = {$version};
{$dependencyline}
PHPFILE);
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

function fail(string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function issueKeys(array $issues): array {
    return array_values(array_filter(array_map(static fn($issue) => $issue->key, $issues)));
}

function assertIssueKeys(array $issues, array $expected): void {
    $actual = issueKeys($issues);
    sort($actual);
    sort($expected);
    if ($actual !== $expected) {
        fail("Expected issue keys:\n" . print_r($expected, true) . "Actual:\n" . print_r($actual, true));
    }
}

function findCheck(array $checks, string $rule, ?string $key = null, ?string $messagecontains = null): ?Check {
    foreach ($checks as $check) {
        if ($check->rule !== $rule) {
            continue;
        }
        if ($key !== null && $check->key !== $key) {
            continue;
        }
        if ($messagecontains !== null && !str_contains($check->message, $messagecontains)) {
            continue;
        }
        return $check;
    }
    return null;
}

function assertCheck(array $checks, string $rule, bool $ok, ?string $key = null, ?string $messagecontains = null): void {
    $check = findCheck($checks, $rule, $key, $messagecontains);
    if ($check === null) {
        fail("Check not found: rule={$rule}, key=" . ($key ?? '[any]') . ', message=' . ($messagecontains ?? '[any]'));
    }
    if ($check->ok !== $ok) {
        fail("Unexpected status for check [{$rule}] {$check->message}");
    }
}

function assertWarning(array $checks, string $rule, ?string $messagecontains = null): void {
    $check = findCheck($checks, $rule, null, $messagecontains);
    if ($check === null) {
        fail("Warning check not found: rule={$rule}, message=" . ($messagecontains ?? '[any]'));
    }
    if (!$check->isWarning()) {
        fail("Expected WARNING for [{$rule}] {$check->message}");
    }
}

function assertNoErrors(array $checks): void {
    $errors = array_values(array_filter($checks, static fn(Check $check): bool => !$check->ok));
    if ($errors !== []) {
        $messages = array_map(static fn(Check $check): string => "[{$check->rule}] {$check->message}", $errors);
        fail("Expected no errors, got:
" . implode("
", $messages));
    }
}

$validator = new Validator();

// Base project: repository files, version metadata, pluginname and optional subplugins check are OK.
$root = createPlugin();
$checks = $validator->validateDetailed($root);
assertCheck($checks, 'repository', true, null, 'license file exists');
assertCheck($checks, 'repository', true, null, 'README file exists');
assertCheck($checks, 'version', true, null, 'version.php exists');
assertCheck($checks, 'version', true, null, 'correctly configured');
assertCheck($checks, 'version', true, null, 'Plugin version is configured');
assertCheck($checks, 'pluginname', true, 'pluginname');
assertCheck($checks, 'subplugin', true, null, 'no nested Moodle subplugins');
assertNoErrors($checks);
assertIssueKeys($validator->validate($root), []);
removeTree($root);

// Missing root files are errors.
$root = createPlugin(['nolicense' => true, 'noreadme' => true]);
$checks = $validator->validateDetailed($root);
assertCheck($checks, 'repository', false, null, 'Missing license file');
assertCheck($checks, 'repository', false, null, 'Missing README file');
removeTree($root);

// Original language rules still work.
$root = createPlugin([
    'access' => "<?php\n\$capabilities = ['local/example:manage' => []];\n",
    'messages' => "<?php\n\$messageproviders = ['notice' => []];\n",
    'caches' => "<?php\n\$definitions = ['result' => []];\n",
]);
assertIssueKeys($validator->validate($root), ['example:manage', 'messageprovider:notice', 'cachedef_result']);
removeTree($root);

// Valid modern + legacy subplugin metadata, bundled subplugin and parent dependency.
$root = createPlugin([
    'lang' => <<<'PHPFILE'
<?php
$string['pluginname'] = 'Example';
$string['subplugintype_exampletype'] = 'Example type';
$string['subplugintype_exampletype_plural'] = 'Example types';
PHPFILE,
    'subplugins' => json_encode([
        'plugintypes' => ['exampletype' => 'local/example/exampletype'],
        'subplugintypes' => ['exampletype' => 'exampletype'],
    ], JSON_PRETTY_PRINT),
]);
createSubplugin($root, 'exampletype', 'alpha');
$checks = $validator->validateDetailed($root);
assertCheck($checks, 'subplugin', true, null, 'valid JSON');
assertCheck($checks, 'subplugin', true, null, "'plugintypes' tag is correctly configured");
assertCheck($checks, 'subplugin', true, null, "'subplugintypes' tag is correctly configured");
assertCheck($checks, 'subplugin', true, null, 'same type keys');
assertCheck($checks, 'subplugin', true, null, 'matching legacy and modern paths');
assertCheck($checks, 'subplugin', true, 'subplugintype_exampletype');
assertCheck($checks, 'subplugin', true, 'subplugintype_exampletype_plural');
assertCheck($checks, 'subplugin', true, null, "correctly identifies itself as 'exampletype_alpha'");
assertCheck($checks, 'subplugin', true, null, "declares dependency on parent 'local_example'");
assertCheck($checks, 'subplugin', true, null, 'parent dependency version');
assertNoErrors($checks);
assertIssueKeys($validator->validate($root), []);
removeTree($root);

// Wrong canonical filename is detected.
$root = createPlugin();
file_put_contents($root . '/db/sub-plugins.json', '{}');
$checks = $validator->validateDetailed($root);
assertCheck($checks, 'subplugin', false, null, 'canonical file name');
removeTree($root);

// Nested Moodle plugin without db/subplugins.json is detected.
$root = createPlugin();
createSubplugin($root, 'exampletype', 'alpha');
$checks = $validator->validateDetailed($root);
assertCheck($checks, 'subplugin', false, null, 'Nested Moodle plugins were detected');
removeTree($root);

// Mismatched plugintypes/subplugintypes keys are rejected.
$root = createPlugin([
    'subplugins' => json_encode([
        'plugintypes' => ['oldtype' => 'local/example/exampletype'],
        'subplugintypes' => ['exampletype' => 'exampletype'],
    ], JSON_PRETTY_PRINT),
]);
mkdir($root . '/exampletype', 0777, true);
$checks = $validator->validateDetailed($root);
assertCheck($checks, 'subplugin', false, null, 'must contain the same subplugin type keys');
removeTree($root);

// Incorrect legacy path is rejected.
$root = createPlugin([
    'subplugins' => json_encode([
        'plugintypes' => ['exampletype' => 'local/example/wrong'],
        'subplugintypes' => ['exampletype' => 'exampletype'],
    ], JSON_PRETTY_PRINT),
]);
mkdir($root . '/exampletype', 0777, true);
$checks = $validator->validateDetailed($root);
assertCheck($checks, 'subplugin', false, null, 'inconsistent paths');
removeTree($root);

// Incorrect subplugin component is rejected.
$root = createPlugin([
    'lang' => "<?php\n\$string['pluginname']='Example';\n\$string['subplugintype_exampletype']='Type';\n\$string['subplugintype_exampletype_plural']='Types';\n",
    'subplugins' => '{"subplugintypes":{"exampletype":"exampletype"}}',
]);
createSubplugin($root, 'exampletype', 'alpha', component: 'wrong_alpha');
$checks = $validator->validateDetailed($root);
assertCheck($checks, 'subplugin', false, null, "component must be 'exampletype_alpha'");
removeTree($root);

// Missing explicit dependency on the parent is rejected.
$root = createPlugin([
    'lang' => "<?php\n\$string['pluginname']='Example';\n\$string['subplugintype_exampletype']='Type';\n\$string['subplugintype_exampletype_plural']='Types';\n",
    'subplugins' => '{"subplugintypes":{"exampletype":"exampletype"}}',
]);
createSubplugin($root, 'exampletype', 'alpha', dependency: false);
$checks = $validator->validateDetailed($root);
assertCheck($checks, 'subplugin', false, null, "must declare a dependency on parent plugin 'local_example'");
removeTree($root);

// A subplugin cannot require a newer parent than the parent bundled in the repository.
$root = createPlugin([
    'version' => 2026081600,
    'lang' => "<?php\n\$string['pluginname']='Example';\n\$string['subplugintype_exampletype']='Type';\n\$string['subplugintype_exampletype_plural']='Types';\n",
    'subplugins' => '{"subplugintypes":{"exampletype":"exampletype"}}',
]);
createSubplugin($root, 'exampletype', 'alpha', parentrequired: 2026081700);
$checks = $validator->validateDetailed($root);
assertCheck($checks, 'subplugin', false, null, 'but the bundled parent version is 2026081600');
removeTree($root);

// Unknown and empty top-level tags are rejected.
$root = createPlugin(['subplugins' => '{"subplugintypes":{},"unexpected":{"x":"y"}}']);
$checks = $validator->validateDetailed($root);
assertCheck($checks, 'subplugin', false, null, "must not be empty");
assertCheck($checks, 'subplugin', false, null, "Unknown top-level tag 'unexpected'");
removeTree($root);

// Empty strings remain errors unless --allow-empty behavior is requested through the API.
$root = createPlugin(['lang' => "<?php\n\$string['pluginname'] = '';\n"]);
assertIssueKeys($validator->validate($root), ['pluginname']);
assertIssueKeys($validator->validate($root, 'en', false), []);
removeTree($root);


// Privacy API language keys are validated statically.
$root = createPlugin([
    'lang' => <<<'PHPFILE'
<?php
$string['pluginname'] = 'Example';
$string['privacy:metadata:local_example_data'] = 'Stored data';
$string['privacy:metadata:local_example_data:userid'] = 'User ID';
PHPFILE,
]);
mkdir($root . '/classes/privacy', 0777, true);
file_put_contents($root . '/classes/privacy/provider.php', <<<'PHPFILE'
<?php
$collection->add_database_table(
    'local_example_data',
    [
        'userid' => 'privacy:metadata:local_example_data:userid',
        'secret' => 'privacy:metadata:local_example_data:secret',
    ],
    'privacy:metadata:local_example_data'
);
PHPFILE);
$checks = $validator->validateDetailed($root);
assertCheck($checks, 'privacy', true, 'privacy:metadata:local_example_data');
assertCheck($checks, 'privacy', true, 'privacy:metadata:local_example_data:userid');
assertCheck($checks, 'privacy', false, 'privacy:metadata:local_example_data:secret');
removeTree($root);

// Literal get_string() calls are validated only when they target the current component.
$root = createPlugin([
    'lang' => <<<'PHPFILE'
<?php
$string['pluginname'] = 'Example';
$string['present'] = 'Present';
PHPFILE,
]);
mkdir($root . '/classes', 0777, true);
file_put_contents($root . '/classes/service.php', <<<'PHPFILE'
<?php
get_string('present', 'local_example');
get_string('missing', 'local_example');
get_string('savechanges');
get_string('pluginname', 'mod_quiz');
get_string($dynamic, 'local_example');
PHPFILE);
$checks = $validator->validateDetailed($root);
assertCheck($checks, 'get_string', true, 'present');
assertCheck($checks, 'get_string', false, 'missing');
if (findCheck($checks, 'get_string', 'savechanges') !== null || findCheck($checks, 'get_string', 'pluginname', "mod_quiz") !== null) {
    fail('Core or foreign-component get_string() calls must not be validated against the current plugin language file.');
}
removeTree($root);

// Legacy AJAX endpoints are warnings and do not fail validate(). Multipart upload handlers are ignored.
$root = createPlugin();
file_put_contents($root . '/ajax.php', "<?php\ndefine('AJAX_SCRIPT', true);\nrequire_once('../../config.php');\n");
file_put_contents($root . '/upload.php', "<?php\ndefine('AJAX_SCRIPT', true);\n\$file = \$_FILES['file'] ?? null;\n");
$checks = $validator->validateDetailed($root);
assertWarning($checks, 'ajax', 'AJAX_SCRIPT endpoint');
assertIssueKeys($validator->validate($root), []);
removeTree($root);

// Direct jQuery AJAX calls to PHP endpoints are warnings.
$root = createPlugin();
mkdir($root . '/amd/src', 0777, true);
file_put_contents($root . '/amd/src/request.js', <<<'JS'
define(['jquery'], function($) {
    $.ajax({url: 'ajax.php', method: 'POST'});
});
JS);
$checks = $validator->validateDetailed($root);
assertWarning($checks, 'ajax', 'Direct jQuery $.ajax() call');
removeTree($root);

// Large inline HTML in JavaScript is a warning, not an error.
$root = createPlugin();
mkdir($root . '/amd/src', 0777, true);
$html = '<div class="panel">' . str_repeat('<span>Example content</span>', 12) . '</div>';
file_put_contents($root . '/amd/src/ui.js', "define([], function() {\n    element.innerHTML = `{$html}`;\n});\n");
$checks = $validator->validateDetailed($root);
assertWarning($checks, 'javascript', 'Large inline HTML fragment');
assertIssueKeys($validator->validate($root), []);
removeTree($root);

echo "All tests passed.\n";
