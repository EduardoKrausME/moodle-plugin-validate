<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate;

final class ValidationContext {
    public function __construct(
        public readonly string $pluginroot,
        public readonly string $component,
        public readonly string $language,
        public readonly LanguageCatalog $catalog,
        public readonly PhpArrayKeyExtractor $extractor,
        public readonly bool $checkempty = true,
    ) {
    }

    public function relative(string $file): string {
        $root = rtrim(str_replace('\\', '/', realpath($this->pluginroot) ?: $this->pluginroot), '/');
        $path = str_replace('\\', '/', realpath($file) ?: $file);
        if (str_starts_with($path, $root . '/')) {
            return substr($path, strlen($root) + 1);
        }
        return $path;
    }

    public function checkRequiredString(
        string $rule,
        string $key,
        string $sourcefile,
        int $sourceline,
        string $reason,
    ): Check {
        if (!$this->catalog->has($key)) {
            return new Check(
                false,
                $rule,
                $this->relative($sourcefile),
                $sourceline,
                $key,
                "Missing language string \$string['{$key}']; {$reason}",
            );
        }

        if ($this->checkempty && $this->catalog->isEmpty($key)) {
            return new Check(
                false,
                $rule,
                $this->relative($this->catalog->file()),
                $this->catalog->line($key),
                $key,
                "Language string \$string['{$key}'] is empty; {$reason}",
            );
        }

        return new Check(
            true,
            $rule,
            $this->relative($sourcefile),
            $sourceline,
            $key,
            "Language string \$string['{$key}'] exists.",
        );
    }

    public function issueForRequiredString(
        string $rule,
        string $key,
        string $sourcefile,
        int $sourceline,
        string $reason,
    ): ?Issue {
        return $this->checkRequiredString($rule, $key, $sourcefile, $sourceline, $reason)->toIssue();
    }
}
