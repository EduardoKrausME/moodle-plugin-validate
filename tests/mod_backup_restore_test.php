<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\Validator;

function createBackupRestorePlugin(string $lib): string {
    $root = sys_get_temp_dir() . '/moodle-plugin-validate-backup-' . bin2hex(random_bytes(6));
    mkdir($root . '/lang/en', 0777, true);

    file_put_contents($root . '/version.php', <<<'PHPFILE'
<?php
$plugin->component = 'mod_example';
$plugin->version = 2026091400;
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

function removeBackupRestorePlugin(string $root): void {
    if (!is_dir($root)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($root);
}

function backupRestoreChecks(array $checks): array {
    return array_values(array_filter(
        $checks,
        static fn(Check $check): bool => $check->rule === 'mod_backup_restore',
    ));
}

function backupRestoreErrors(array $checks): array {
    return array_values(array_filter(
        backupRestoreChecks($checks),
        static fn(Check $check): bool => $check->isError(),
    ));
}

function writeValidBackupRestoreFiles(string $root): void {
    mkdir($root . '/backup/moodle2', 0777, true);

    file_put_contents($root . '/backup/moodle2/backup_example_activity_task.class.php', <<<'PHPFILE'
<?php
class backup_example_activity_task extends backup_activity_task {
    protected function define_my_steps() {
        $this->add_step(new backup_example_activity_structure_step('example_structure', 'example.xml'));
    }
}
PHPFILE);

    file_put_contents($root . '/backup/moodle2/backup_example_stepslib.php', <<<'PHPFILE'
<?php
class backup_example_activity_structure_step extends backup_activity_structure_step {
    protected function define_structure() {
        $example = new backup_nested_element('example', ['id'], ['name']);
        return $this->prepare_activity_structure($example);
    }
}
PHPFILE);

    file_put_contents($root . '/backup/moodle2/restore_example_activity_task.class.php', <<<'PHPFILE'
<?php
class restore_example_activity_task extends restore_activity_task {
    protected function define_my_steps() {
        $this->add_step(new restore_example_activity_structure_step('example_structure', 'example.xml'));
    }
}
PHPFILE);

    file_put_contents($root . '/backup/moodle2/restore_example_stepslib.php', <<<'PHPFILE'
<?php
class restore_example_activity_structure_step extends restore_activity_structure_step {
    protected function define_structure() {
        $paths = [];
        $paths[] = new restore_path_element('example', '/activity/example');
        return $this->prepare_activity_structure($paths);
    }
}
PHPFILE);
}

$validator = new Validator();

// FEATURE_BACKUP_MOODLE2=true requires all four canonical Moodle backup/restore files.
$root = createBackupRestorePlugin(<<<'PHPFILE'
function example_supports($feature) {
    return match ($feature) {
        FEATURE_BACKUP_MOODLE2 => true,
        default => null,
    };
}
PHPFILE);

$errors = backupRestoreErrors($validator->validateDetailed($root));
removeBackupRestorePlugin($root);

if (count($errors) !== 4) {
    fwrite(STDERR, 'Expected four missing backup/restore file errors, got ' . count($errors) . ".\n");
    exit(1);
}

// A complete canonical backup/restore implementation passes the static checks.
$root = createBackupRestorePlugin(<<<'PHPFILE'
function example_supports($feature) {
    switch ($feature) {
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}
PHPFILE);
writeValidBackupRestoreFiles($root);

$errors = backupRestoreErrors($validator->validateDetailed($root));
removeBackupRestorePlugin($root);

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "[{$error->rule}] {$error->file}:{$error->line} {$error->message}\n");
    }
    exit(1);
}

// Broken restore structure is detected even when all four files exist.
$root = createBackupRestorePlugin(<<<'PHPFILE'
function example_supports($feature) {
    if ($feature === FEATURE_BACKUP_MOODLE2) {
        return true;
    }
    return null;
}
PHPFILE);
writeValidBackupRestoreFiles($root);
file_put_contents($root . '/backup/moodle2/restore_example_stepslib.php', <<<'PHPFILE'
<?php
class restore_example_activity_structure_step extends restore_activity_structure_step {
    protected function define_structure() {
        $paths = [];
        $paths[] = new restore_path_element('example', '/activity/example');
        return $paths;
    }
}
PHPFILE);

$errors = backupRestoreErrors($validator->validateDetailed($root));
removeBackupRestorePlugin($root);

$prepareerror = array_values(array_filter(
    $errors,
    static fn(Check $check): bool => str_starts_with($check->key, 'prepare:'),
));
if (count($prepareerror) !== 1) {
    fwrite(STDERR, "Expected restore define_structure() without prepare_activity_structure() to fail.\n");
    exit(1);
}

echo "mod backup/restore validation test passed.\n";
