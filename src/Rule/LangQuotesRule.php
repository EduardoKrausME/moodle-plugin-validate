<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\ValidationContext;
use RuntimeException;

/**
 * Enforces single-quoted literal keys and values in Moodle language files.
 */
final class LangQuotesRule implements RuleInterface {
    public function name(): string {
        return "langquotes";
    }

    /** @return Check[] */
    public function validate(ValidationContext $context): array {
        $files = glob($context->pluginroot . "/lang/*/*.php") ?: [];
        sort($files, SORT_STRING);
        $checks = [];

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $contents = file_get_contents($file);
            if ($contents === false) {
                throw new RuntimeException("Unable to read language file {$file}");
            }

            $tokens = token_get_all($contents);
            $count = count($tokens);
            $found = 0;
            $invalid = 0;

            for ($i = 0; $i < $count; $i++) {
                $token = $tokens[$i];
                if (!is_array($token) || $token[0] !== T_VARIABLE || $token[1] !== "\$string") {
                    continue;
                }

                $open = $this->nextSignificant($tokens, $i + 1);
                if ($open === null || $tokens[$open] !== "[") {
                    continue;
                }

                $keyindex = $this->nextSignificant($tokens, $open + 1);
                $close = $keyindex === null ? null : $this->nextSignificant($tokens, $keyindex + 1);
                $equals = $close === null ? null : $this->nextSignificant($tokens, $close + 1);
                if ($close === null || $tokens[$close] !== "]" || $equals === null || $tokens[$equals] !== "=") {
                    continue;
                }

                $valueindex = $this->nextSignificant($tokens, $equals + 1);
                $end = $valueindex === null ? null : $this->nextSignificant($tokens, $valueindex + 1);
                $keytoken = $tokens[$keyindex];
                $valuetoken = $valueindex === null ? null : $tokens[$valueindex];
                $keyvalid = $this->isSingleQuotedLiteral($keytoken);
                $valuevalid = $valuetoken !== null && $this->isSingleQuotedLiteral($valuetoken);
                $simplevalue = $end !== null && $tokens[$end] === ";";
                $key = is_array($keytoken) && $keytoken[0] === T_CONSTANT_ENCAPSED_STRING
                    ? substr($keytoken[1], 1, -1)
                    : "";

                $found++;
                if ($keyvalid && $valuevalid && $simplevalue) {
                    continue;
                }

                $invalid++;
                $checks[] = new Check(
                    false,
                    $this->name(),
                    $context->relative($file),
                    $token[2],
                    $key,
                    "Language assignments must use single-quoted literal keys and values: "
                        . "\$string['key'] = 'value';. Double quotes, interpolation and "
                        . "concatenated or dynamic values are not accepted.",
                    null,
                    true,
                );
            }

            if ($found > 0 && $invalid === 0) {
                $checks[] = new Check(
                    true,
                    $this->name(),
                    $context->relative($file),
                    1,
                    "",
                    "All {$found} language string assignments use single-quoted keys and values.",
                );
            }
        }

        return $checks;
    }

    /** @param array|string $token */
    private function isSingleQuotedLiteral(array|string $token): bool {
        return is_array($token)
            && $token[0] === T_CONSTANT_ENCAPSED_STRING
            && str_starts_with($token[1], "'");
    }

    /**
     * @param array<int, array|string> $tokens
     */
    private function nextSignificant(array $tokens, int $start): ?int {
        for ($i = $start, $count = count($tokens); $i < $count; $i++) {
            if (!is_array($tokens[$i])) {
                return $i;
            }
            if (!in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $i;
            }
        }
        return null;
    }
}
