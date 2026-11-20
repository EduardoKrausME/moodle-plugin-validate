<?php

declare(strict_types=1);

require dirname(__DIR__) . "/autoload.php";

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\Validator;

$checks = [
    [str_starts_with("", ""), true],
    [str_starts_with("moodle", ""), true],
    [str_starts_with("moodle", "mood"), true],
    [str_starts_with("moodle", "oodle"), false],
    [str_ends_with("", ""), true],
    [str_ends_with("moodle", ""), true],
    [str_ends_with("moodle", "dle"), true],
    [str_ends_with("moodle", "moodle-dev"), false],
    [str_contains("moodle", ""), true],
    [str_contains("moodle", "ood"), true],
    [str_contains("moodle", "PHP"), false],
];

foreach ($checks as $index => $case) {
    if ($case[0] !== $case[1]) {
        throw new RuntimeException("PHP string compatibility check failed at case " . $index);
    }
}

if (!defined("T_NAME_QUALIFIED") || !defined("T_NAME_FULLY_QUALIFIED")) {
    throw new RuntimeException("Missing PHP token compatibility constants");
}

$check = new Check(false, "version", "version.php", 1, "", "Test issue");
$issue = $check->toIssue();
if ($issue === null || $issue->rule !== "version" || $issue->file !== "version.php") {
    throw new RuntimeException("Class autoloading or legacy constructor compatibility failed");
}

$validator = new Validator();
if (!($validator instanceof Validator)) {
    throw new RuntimeException("Validator class failed to load");
}

echo "PHP 7.4 compatibility smoke tests passed.\n";
