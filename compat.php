<?php

declare(strict_types=1);

// Keep the standalone GitHub Action compatible with the PHP 7.4 runtime used by Moodle 3.10.
// The native PHP 8 implementations take precedence when they are available.
if (!function_exists("str_starts_with")) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === "" || strpos($haystack, $needle) === 0;
    }
}

if (!function_exists("str_contains")) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === "" || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists("str_ends_with")) {
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
    }
}

/**
 * PHP 8 introduced qualified-name tokens. PHP 7.4 emits T_STRING and
 * T_NS_SEPARATOR instead, so these sentinel IDs can never match a real token.
 * Keep the PHP source inspector operational without redefining native tokens.
 */
if (!defined("T_NAME_QUALIFIED")) {
    define("T_NAME_QUALIFIED", -1);
}
if (!defined("T_NAME_FULLY_QUALIFIED")) {
    define("T_NAME_FULLY_QUALIFIED", -2);
}
