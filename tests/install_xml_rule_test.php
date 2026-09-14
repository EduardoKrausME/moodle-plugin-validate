<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\LanguageCatalog;
use EduardoKraus\MoodleStringValidate\PhpArrayKeyExtractor;
use EduardoKraus\MoodleStringValidate\Rule\InstallXmlRule;
use EduardoKraus\MoodleStringValidate\ValidationContext;

function installXmlCreateContext(string $component, string $xml): ValidationContext {
    $root = sys_get_temp_dir() . '/moodle-installxml-' . bin2hex(random_bytes(6));
    mkdir($root . '/db', 0777, true);
    mkdir($root . '/lang/en', 0777, true);

    $languagecomponent = str_starts_with($component, 'mod_') ? substr($component, 4) : $component;
    $languagefile = $root . '/lang/en/' . $languagecomponent . '.php';
    file_put_contents($languagefile, "<?php\n\$string['pluginname'] = 'Example';\n");
    file_put_contents($root . '/db/install.xml', $xml);

    return new ValidationContext(
        $root,
        $component,
        'en',
        new LanguageCatalog($languagefile),
        new PhpArrayKeyExtractor(),
        true,
    );
}

function installXmlRemoveTree(string $directory): void {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
}

/** @param Check[] $checks */
function installXmlAssertError(array $checks, string $message): void {
    foreach ($checks as $check) {
        if ($check->isError() && str_contains($check->message, $message)) {
            return;
        }
    }
    fwrite(STDERR, "Expected InstallXmlRule error containing: {$message}\n");
    exit(1);
}

/** @param Check[] $checks */
function installXmlAssertNoErrors(array $checks): void {
    foreach ($checks as $check) {
        if ($check->isError()) {
            fwrite(STDERR, "Unexpected InstallXmlRule error: {$check->message}\n");
            exit(1);
        }
    }
}

$rule = new InstallXmlRule();

$validxml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="local/example/db" VERSION="20260914">
  <TABLES>
    <TABLE NAME="local_example_data">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="userid" UNIQUE="false" FIELDS="userid"/>
      </INDEXES>
    </TABLE>
  </TABLES>
</XMLDB>
XML;
$context = installXmlCreateContext('local_example', $validxml);
installXmlAssertNoErrors($rule->validate($context));
installXmlRemoveTree($context->pluginroot);

$invalidxml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="local/example/db" VERSION="20260914">
  <TABLES>
    <TABLE NAME="wrong_table_name_that_is_far_too_long_for_the_moodle_xmldb_table_limit_1234567890">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="name" TYPE="char" LENGTH="255" NOTNULL="true" DEFAULT="" SEQUENCE="false"/>
        <FIELD NAME="name" TYPE="char" LENGTH="255" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="field_name_that_is_far_too_long_for_the_sixty_three_character_xmldb_limit_1234567890" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="missingid"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="name" UNIQUE="false" FIELDS="name"/>
        <INDEX NAME="name" UNIQUE="false" FIELDS="name"/>
        <INDEX NAME="missing" UNIQUE="false" FIELDS="missingfield"/>
      </INDEXES>
    </TABLE>
  </TABLES>
</XMLDB>
XML;
$context = installXmlCreateContext('local_example', $invalidxml);
$checks = $rule->validate($context);
installXmlAssertError($checks, 'is too long');
installXmlAssertError($checks, "must use plugin table prefix 'local_example'");
installXmlAssertError($checks, "Duplicate field 'name'");
installXmlAssertError($checks, 'NOTNULL but declares DEFAULT=""');
installXmlAssertError($checks, "Field 'wrong_table_name_that_is_far_too_long_for_the_moodle_xmldb_table_limit_1234567890.id' must use SEQUENCE=\"true\"");
installXmlAssertError($checks, "references missing field 'missingid'");
installXmlAssertError($checks, "Duplicate index 'name'");
installXmlAssertError($checks, "duplicates index 'name'");
installXmlAssertError($checks, "references missing field 'missingfield'");
installXmlAssertError($checks, "primary key must reference only the 'id' field");
installXmlRemoveTree($context->pluginroot);

$modxml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="mod/pulse/db" VERSION="20260914">
  <TABLES>
    <TABLE NAME="pulse_votes">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
      </KEYS>
    </TABLE>
  </TABLES>
</XMLDB>
XML;
$context = installXmlCreateContext('mod_pulse', $modxml);
installXmlAssertError($rule->validate($context), "must define its main table 'pulse'");
installXmlRemoveTree($context->pluginroot);

$modmainxml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="mod/pulse/db" VERSION="20260914">
  <TABLES>
    <TABLE NAME="pulse">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="name" TYPE="char" LENGTH="255" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="intro" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
      </KEYS>
    </TABLE>
  </TABLES>
</XMLDB>
XML;
$context = installXmlCreateContext('mod_pulse', $modmainxml);
$checks = $rule->validate($context);
installXmlAssertError($checks, "missing required field 'course'");
installXmlAssertError($checks, "missing required field 'timemodified'");
installXmlAssertError($checks, "missing 'introformat'");
installXmlRemoveTree($context->pluginroot);

$brokenxml = '<XMLDB><TABLES><TABLE NAME="local_example"><FIELDS></TABLE></TABLES></XMLDB>';
$context = installXmlCreateContext('local_example', $brokenxml);
installXmlAssertError($rule->validate($context), 'Invalid XML in db/install.xml');
installXmlRemoveTree($context->pluginroot);

echo "InstallXmlRule tests passed.\n";
