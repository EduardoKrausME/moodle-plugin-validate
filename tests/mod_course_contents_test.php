<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use EduardoKraus\MoodleStringValidate\Validator;

function createModPlugin(string $lib): string {
    $root = sys_get_temp_dir() . '/moodle-string-validate-mod-contents-' . bin2hex(random_bytes(6));
    mkdir($root . '/lang/en', 0777, true);

    file_put_contents($root . '/version.php', <<<'PHPFILE'
<?php
$plugin->component = 'mod_example';
$plugin->version = 2026081800;
PHPFILE);

    file_put_contents($root . '/lang/en/example.php', <<<'PHPFILE'
<?php
$string['pluginname'] = 'Example activity';
PHPFILE);

    file_put_contents($root . '/LICENSE', "GNU GPL v3 or later\n");
    file_put_contents($root . '/README.md', "# Example activity\n");
    file_put_contents($root . '/lib.php', "<?php\n" . $lib);

    return $root;
}

function removeModPlugin(string $root): void {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($root);
}

function findModCheck(array $checks, string $key, string $message): ?object {
    foreach ($checks as $check) {
        if ($check->rule === 'mod_course_contents' && $check->key === $key && str_contains($check->message, $message)) {
            return $check;
        }
    }
    return null;
}

$validator = new Validator();

// Invalid get_coursemodule_info fields and incompatible supports() return values.
$root = createModPlugin(<<<'PHPFILE'
function example_supports($feature) {
    return match ($feature) {
        FEATURE_GROUPS => 1,
        FEATURE_COMPLETION => 'yes',
        FEATURE_MOD_ARCHETYPE => true,
        FEATURE_MOD_PURPOSE => true,
        FEATURE_MOD_OTHERPURPOSE => false,
        FEATURE_GROUPMEMBERSONLY => true,
        default => true,
    };
}

function example_get_coursemodule_info($cm) {
    $info = new cached_cm_info();
    $info->name = 123;
    $info->purpose = true;
    return $info;
}
PHPFILE);

$checks = $validator->validateDetailed($root);
if (($check = findModCheck($checks, 'purpose', "Property 'purpose' is not a valid")) === null || $check->ok) {
    fwrite(STDERR, "Expected invalid get_coursemodule_info() purpose field.\n");
    exit(1);
}
if (($check = findModCheck($checks, 'name', 'expected string, got int')) === null || $check->ok) {
    fwrite(STDERR, "Expected invalid literal type for get_coursemodule_info() name.\n");
    exit(1);
}
if (($check = findModCheck($checks, 'supports:FEATURE_GROUPS', 'must return bool or null')) === null || $check->ok) {
    fwrite(STDERR, "Expected FEATURE_GROUPS integer return to be rejected.\n");
    exit(1);
}
if (($check = findModCheck($checks, 'supports:FEATURE_COMPLETION', 'must return bool or null')) === null || $check->ok) {
    fwrite(STDERR, "Expected FEATURE_COMPLETION string return to be rejected.\n");
    exit(1);
}
if (($check = findModCheck($checks, 'supports:FEATURE_MOD_ARCHETYPE', 'must return MOD_ARCHETYPE_')) === null || $check->ok) {
    fwrite(STDERR, "Expected FEATURE_MOD_ARCHETYPE boolean return to be rejected.\n");
    exit(1);
}
if (($check = findModCheck($checks, 'supports:FEATURE_MOD_PURPOSE', 'must return MOD_PURPOSE_')) === null || $check->ok) {
    fwrite(STDERR, "Expected FEATURE_MOD_PURPOSE boolean return to be rejected.\n");
    exit(1);
}
if (($check = findModCheck($checks, 'supports:FEATURE_MOD_OTHERPURPOSE', 'must return MOD_PURPOSE_')) === null || $check->ok) {
    fwrite(STDERR, "Expected FEATURE_MOD_OTHERPURPOSE boolean return to be rejected.\n");
    exit(1);
}
if (($check = findModCheck($checks, 'supports:FEATURE_GROUPMEMBERSONLY', 'deprecated')) === null || $check->ok) {
    fwrite(STDERR, "Expected deprecated FEATURE_GROUPMEMBERSONLY to be rejected.\n");
    exit(1);
}
if (($check = findModCheck($checks, 'supports:default', 'default return')) === null || $check->ok) {
    fwrite(STDERR, "Expected non-null supports() default return to be rejected.\n");
    exit(1);
}
removeModPlugin($root);

// Valid match implementation.
$root = createModPlugin(<<<'PHPFILE'
function example_supports($feature) {
    return match ($feature) {
        FEATURE_GRADE_HAS_GRADE => true,
        FEATURE_GROUPS => true,
        FEATURE_GROUPINGS => false,
        FEATURE_COMPLETION => true,
        FEATURE_MOD_INTRO => true,
        FEATURE_BACKUP_MOODLE2 => true,
        FEATURE_MOD_ARCHETYPE => MOD_ARCHETYPE_RESOURCE,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_CONTENT,
        FEATURE_MOD_OTHERPURPOSE => MOD_PURPOSE_COLLABORATION,
        default => null,
    };
}

function example_get_coursemodule_info($cm) {
    global $DB;

    if (!$record = $DB->get_record('example', ['id' => $cm->instance])) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $record->name;
    $info->content = $record->intro;
    $info->customdata = ['exampleid' => $record->id];
    return $info;
}
PHPFILE);

$checks = $validator->validateDetailed($root);
$errors = array_values(array_filter(
    $checks,
    static fn($check): bool => $check->rule === 'mod_course_contents' && !$check->ok
));
removeModPlugin($root);

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "[{$error->rule}] {$error->message}\n");
    }
    exit(1);
}

// Valid switch implementation is also parsed.
$root = createModPlugin(<<<'PHPFILE'
function example_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_OTHER;
        case FEATURE_MOD_PURPOSE:
            return 'content';
        default:
            return null;
    }
}
PHPFILE);

$checks = $validator->validateDetailed($root);
$errors = array_values(array_filter(
    $checks,
    static fn($check): bool => $check->rule === 'mod_course_contents' && !$check->ok
));
removeModPlugin($root);

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "[{$error->rule}] {$error->message}\n");
    }
    exit(1);
}


// Invalid if-style fallback is also detected.
$root = createModPlugin(<<<'PHPFILE'
function example_supports($feature) {
    if ($feature === FEATURE_GROUPS) {
        return true;
    }
    return true;
}
PHPFILE);

$checks = $validator->validateDetailed($root);
if (($check = findModCheck($checks, 'supports:fallback', 'fallback return')) === null || $check->ok) {
    fwrite(STDERR, "Expected non-null if-style supports() fallback return to be rejected.\n");
    exit(1);
}
removeModPlugin($root);

echo "mod course contents and supports validation test passed.\n";
