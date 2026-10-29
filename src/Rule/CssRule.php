<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\ValidationContext;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Validates Moodle's automatically loaded plugin styles.css convention.
 */
final class CssRule implements RuleInterface {
    public function name(): string {
        return "css";
    }

    /** @return Check[] */
    public function validate(ValidationContext $context): array {
        if ($context->ignores($this->name())) {
            return [new Check(true, $this->name(), ".", 1, "", "CSS checks ignored by configuration.")];
        }

        $checks = [];
        if (is_file($context->pluginroot . "/style.css")) {
            $checks[] = new Check(
                false,
                $this->name(),
                "style.css",
                1,
                "style.css",
                "Plugin root style.css is not loaded automatically by Moodle. Rename it to styles.css.",
            );
        }

        foreach ($this->phpFiles($context->pluginroot) as $file) {
            $source = file_get_contents($file);
            if ($source === false) {
                continue;
            }
            $tokens = token_get_all($source);
            $count = count($tokens);
            for ($i = 0; $i < $count; $i++) {
                if (!$this->isToken($tokens[$i], T_VARIABLE, '$PAGE')) {
                    continue;
                }
                $operator1 = $this->nextSignificant($tokens, $i + 1);
                $requires = $operator1 === null ? null : $this->nextSignificant($tokens, $operator1 + 1);
                $operator2 = $requires === null ? null : $this->nextSignificant($tokens, $requires + 1);
                $css = $operator2 === null ? null : $this->nextSignificant($tokens, $operator2 + 1);
                $open = $css === null ? null : $this->nextSignificant($tokens, $css + 1);
                if ($operator1 === null || !$this->isToken($tokens[$operator1], T_OBJECT_OPERATOR)
                    || $requires === null || !$this->isToken($tokens[$requires], T_STRING, "requires")
                    || $operator2 === null || !$this->isToken($tokens[$operator2], T_OBJECT_OPERATOR)
                    || $css === null || !$this->isToken($tokens[$css], T_STRING, "css")
                    || $open === null || $tokens[$open] !== '(') {
                    continue;
                }

                if ($this->loadsRootStyles($tokens, $open, $context->component)) {
                    $checks[] = new Check(
                        false,
                        $this->name(),
                        $context->relative($file),
                        $tokens[$i][2],
                        "styles.css",
                        "Do not call \$PAGE->requires->css() for the plugin root styles.css; Moodle loads it automatically.",
                    );
                }
            }
        }

        return $checks ?: [new Check(true, $this->name(), ".", 1, "", "Plugin root CSS filename and loading follow Moodle conventions.")];
    }

    /**
     * Inspects only actual PHP method arguments, never comments or quoted PHP examples.
     * The referenced file must be a Moodle plugin root styles.css, not a nested CSS asset.
     *
     * @param array<int, array|string> $tokens
     */
    private function loadsRootStyles(array $tokens, int $opening, string $component): bool {
        $depth = 0;
        for ($i = $opening, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token === '(') {
                $depth++;
            } else if ($token === ')') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            } else if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                if ($this->isPluginRootStyles(substr($token[1], 1, -1), $component)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function isPluginRootStyles(string $value, string $component): bool {
        $value = preg_replace('/[?#].*$/', '', str_replace('\\/', '/', $value)) ?? $value;
        if ($value === "styles.css" || $value === "/styles.css") {
            return true;
        }

        $parts = explode("_", $component, 2);
        if (count($parts) !== 2) {
            return false;
        }
        [$type, $name] = $parts;
        $directories = [
            "block" => "blocks",
            "tool" => "admin/tool",
            "qtype" => "question/type",
            "qformat" => "question/format",
            "qbehaviour" => "question/behaviour",
            "format" => "course/format",
            "assignsubmission" => "mod/assign/submission",
            "assignfeedback" => "mod/assign/feedback",
            "quizaccess" => "mod/quiz/accessrule",
            "quiz" => "mod/quiz/report",
        ];
        $directory = $directories[$type] ?? $type;
        if (str_ends_with($value, "/{$directory}/{$name}/styles.css")) {
            return true;
        }

        // References to other plugins also target auto-loaded root stylesheets.
        $pluginpaths = [
            "local", "mod", "blocks", "theme", "auth", "enrol", "filter",
            "report", "repository", "portfolio", "plagiarism", "availability",
            "media", "editor", "search", "admin/tool", "question/type",
            "question/format", "question/behaviour", "course/format",
            "mod/assign/submission", "mod/assign/feedback", "mod/quiz/accessrule", "mod/quiz/report",
        ];
        foreach ($pluginpaths as $path) {
            if (preg_match('~(?:^|/)' . preg_quote($path, '~') . '/[a-z][a-z0-9_]*/styles\.css$~i', $value)) {
                return true;
            }
        }
        return false;
    }

    /** @return string[] */
    private function phpFiles(string $root): array {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if (!$item->isFile() || strtolower($item->getExtension()) !== "php") {
                continue;
            }
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1));
            if (str_starts_with($relative, "vendor/") || str_starts_with($relative, ".git/")
                || str_starts_with($relative, "node_modules/")) {
                continue;
            }
            $files[] = $item->getPathname();
        }
        sort($files);
        return $files;
    }

    /** @param array<int, array|string> $tokens */
    private function nextSignificant(array $tokens, int $start): ?int {
        for ($i = $start, $count = count($tokens); $i < $count; $i++) {
            if (!is_array($tokens[$i]) || !in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $i;
            }
        }
        return null;
    }

    /** @param array|string $token */
    private function isToken($token, int $type, ?string $text = null): bool {
        return is_array($token) && $token[0] === $type && ($text === null || $token[1] === $text);
    }
}
