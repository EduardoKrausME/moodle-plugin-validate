<?php

declare(strict_types=1);

require dirname(__DIR__) . "/autoload.php";

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\LanguageCatalog;
use EduardoKraus\MoodleStringValidate\PhpArrayKeyExtractor;
use EduardoKraus\MoodleStringValidate\Rule\InstallXmlHeaderRule;
use EduardoKraus\MoodleStringValidate\ValidationContext;

function headerContext(string $component, string $xml): ValidationContext {
    $root = sys_get_temp_dir() . "/moodle-installxml-header-" . bin2hex(random_bytes(6));
    mkdir($root . "/db", 0777, true);
    mkdir($root . "/lang/en", 0777, true);
    $languagecomponent = str_starts_with($component, "mod_") ? substr($component, 4) : $component;
    $languagefile = $root . "/lang/en/{$languagecomponent}.php";
    file_put_contents($languagefile, "<?php\n\$string['pluginname'] = 'Example';\n");
    file_put_contents($root . "/db/install.xml", $xml);

    return new ValidationContext(
        $root,
        $component,
        "en",
        new LanguageCatalog($languagefile),
        new PhpArrayKeyExtractor(),
        true,
    );
}

function headerRemoveTree(string $root): void {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($root);
}

/** @param Check[] $checks */
function headerAssertNoErrors(array $checks): void {
    foreach ($checks as $check) {
        if ($check->isError()) {
            fwrite(STDERR, "Unexpected [{$check->rule}] error: {$check->message}\n");
            exit(1);
        }
    }
}

/** @param Check[] $checks */
function headerAssertError(array $checks, string $needle): void {
    foreach ($checks as $check) {
        if ($check->isError() && str_contains($check->message, $needle)) {
            return;
        }
    }
    fwrite(STDERR, "Expected install.xml header error containing: {$needle}\n");
    exit(1);
}

$rule = new InstallXmlHeaderRule();

$modxml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="mod/example/db" VERSION="20260915" COMMENT="XMLDB file for mod/example"
       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:noNamespaceSchemaLocation="../../../lib/xmldb/xmldb.xsd">
</XMLDB>
XML;
$context = headerContext("mod_example", $modxml);
headerAssertNoErrors($rule->validate($context));
headerRemoveTree($context->pluginroot);

$localxml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="local/example/db" VERSION="20260915" COMMENT="XMLDB file for local/example"
       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:noNamespaceSchemaLocation="../../../lib/xmldb/xmldb.xsd">
</XMLDB>
XML;
$context = headerContext("local_example", $localxml);
headerAssertNoErrors($rule->validate($context));
headerRemoveTree($context->pluginroot);

$mediaxml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="media/player/example/db" VERSION="20260915" COMMENT="XMLDB file for media/player/example"
       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:noNamespaceSchemaLocation="../../../../lib/xmldb/xmldb.xsd">
</XMLDB>
XML;
$context = headerContext("media_example", $mediaxml);
headerAssertNoErrors($rule->validate($context));
headerRemoveTree($context->pluginroot);

$profilefieldxml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="user/profile/field/database/db" VERSION="20260915" COMMENT="XMLDB file for profilefield_database"
       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:noNamespaceSchemaLocation="../../../../../lib/xmldb/xmldb.xsd">
</XMLDB>
XML;
$context = headerContext("profilefield_database", $profilefieldxml);
headerAssertNoErrors($rule->validate($context));
headerRemoveTree($context->pluginroot);

$badprofilefieldxml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="user/profile/field/database/db" VERSION="20260915" COMMENT="XMLDB file for profilefield_database"
       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:noNamespaceSchemaLocation="../../../../lib/xmldb/xmldb.xsd">
</XMLDB>
XML;
$context = headerContext("profilefield_database", $badprofilefieldxml);
headerAssertError($rule->validate($context), "must be '../../../../../lib/xmldb/xmldb.xsd'");
headerRemoveTree($context->pluginroot);

$badmediaxml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="media/example/db" VERSION="20261342" COMMENT=""
       xmlns:xsi="wrong"
       xsi:noNamespaceSchemaLocation="../../../lib/xmldb/xmldb.xsd">
</XMLDB>
XML;
$context = headerContext("media_example", $badmediaxml);
$checks = $rule->validate($context);
headerAssertError($checks, "must be 'media/player/example/db'");
headerAssertError($checks, "VERSION must be a valid YYYYMMDD date");
headerAssertError($checks, "non-empty COMMENT");
headerAssertError($checks, "must declare xmlns:xsi");
headerRemoveTree($context->pluginroot);

$wrongdepthxml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="media/player/example/db" VERSION="20260915" COMMENT="Example"
       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:noNamespaceSchemaLocation="../../../lib/xmldb/xmldb.xsd">
</XMLDB>
XML;
$context = headerContext("media_example", $wrongdepthxml);
headerAssertError($rule->validate($context), "must be '../../../../lib/xmldb/xmldb.xsd'");
headerRemoveTree($context->pluginroot);

echo "InstallXmlHeaderRule tests passed.\n";
