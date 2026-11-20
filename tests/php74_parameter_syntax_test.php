<?php

declare(strict_types=1);

// PHP 7.4 rejects trailing commas in declared parameter lists, even though PHP 8 accepts them.
// Keep this guard in the PHP 8 CI suite so regressions fail before the action is distributed.
$root = dirname(__DIR__);
$files = [
    $root . "/autoload.php",
    $root . "/compat.php",
    $root . "/bin/moodle-string-validate",
];

foreach (["src", "tests"] as $directory) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . "/" . $directory, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === "php") {
            $files[] = $file->getPathname();
        }
    }
}

foreach ($files as $file) {
    $source = file_get_contents($file);
    if ($source === false) {
        throw new RuntimeException("Could not read " . $file);
    }
    $tokens = token_get_all($source);
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || !in_array($tokens[$i][0], [T_FUNCTION, T_FN], true)) {
            continue;
        }

        // Skip the function name, optional reference marker and comments.
        $open = null;
        for ($j = $i + 1; $j < $count; $j++) {
            if ($tokens[$j] === "(") {
                $open = $j;
                break;
            }
            if ($tokens[$j] === "{" || $tokens[$j] === ";") {
                break;
            }
        }
        if ($open === null) {
            continue;
        }

        $depth = 1;
        $previous = null;
        for ($j = $open + 1; $j < $count; $j++) {
            $token = $tokens[$j];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if ($token === "(") {
                $depth++;
            } elseif ($token === ")") {
                $depth--;
                if ($depth === 0) {
                    if ($previous === ",") {
                        throw new RuntimeException("PHP 7.4 incompatible trailing parameter comma in " . $file);
                    }
                    break;
                }
            }
            $previous = $token;
        }
    }
}

echo "PHP 7.4 parameter declarations are compatible.\n";
