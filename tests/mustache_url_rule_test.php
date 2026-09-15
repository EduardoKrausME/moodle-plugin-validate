<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use EduardoKraus\MoodleStringValidate\Validator;

$root = sys_get_temp_dir() . '/moodle-string-validate-mustache-url-' . bin2hex(random_bytes(6));
mkdir($root . '/lang/en', 0777, true);
mkdir($root . '/templates', 0777, true);

file_put_contents($root . '/version.php', <<<'PHPFILE'
<?php
$plugin->component = 'local_example';
$plugin->version = 2026091500;
PHPFILE);
file_put_contents($root . '/LICENSE', "GNU GPL v3 or later\n");
file_put_contents($root . '/README.md', "# Example\n");
file_put_contents($root . '/lang/en/local_example.php', <<<'PHPFILE'
<?php
$string['pluginname'] = 'Example';
PHPFILE);
file_put_contents($root . '/templates/example.mustache', <<<'MUSTACHE'
<a href="{{config.url}}">Bad href config.url</a>
<a href="{{.}}">Bad href dot</a>
<img src="{{config.url}}" alt="Bad src config.url">
<img src="{{.}}" alt="Bad src dot">
<a href="{{{config.url}}}">Good href config.url</a>
<a href="{{{.}}}">Good href dot</a>
<img src="{{{config.url}}}" alt="Good src config.url">
<img src="{{{.}}}" alt="Good src dot">
<a data-url="{{config.url}}">Ignored attribute</a>
<span>{{config.url}}</span>
<a href="/course/view.php?id={{id}}">Embedded expression is not this rule</a>
MUSTACHE);

$checks = (new Validator())->validateDetailed($root);
$errors = array_values(array_filter(
    $checks,
    static fn($check): bool => $check->rule === 'mustacheurl' && $check->isError(),
));

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST,
);
foreach ($iterator as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}
rmdir($root);

if (count($errors) !== 4) {
    fwrite(STDERR, 'Expected 4 Mustache URL errors, got ' . count($errors) . "\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "[{$error->file}:{$error->line}] key={$error->key} {$error->message}\n");
    }
    exit(1);
}

$expected = [
    ['key' => 'config.url', 'line' => 1],
    ['key' => '.', 'line' => 2],
    ['key' => 'config.url', 'line' => 3],
    ['key' => '.', 'line' => 4],
];

foreach ($expected as $index => $item) {
    if ($errors[$index]->key !== $item['key'] || $errors[$index]->line !== $item['line']) {
        fwrite(
            STDERR,
            "Unexpected error #{$index}: key={$errors[$index]->key}, line={$errors[$index]->line}\n",
        );
        exit(1);
    }
    if (!str_contains($errors[$index]->message, 'must use triple braces')) {
        fwrite(STDERR, "Expected triple-braces message was not found.\n");
        exit(1);
    }
}

echo "Mustache URL rule test passed.\n";
