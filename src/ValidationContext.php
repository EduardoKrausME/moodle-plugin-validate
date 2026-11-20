<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate;

/**
 * Class ValidationContext.
 */
final class ValidationContext {
    public string $pluginroot;
    public string $component;
    public string $language;
    public LanguageCatalog $catalog;
    public PhpArrayKeyExtractor $extractor;
    public bool $checkempty;
    public array $ignoredChecks;

    /**
     * Method __construct.
     *
     * @param string $pluginroot Parameter pluginroot.
     * @param string $component Parameter component.
     * @param string $language Parameter language.
     * @param LanguageCatalog $catalog Parameter catalog.
     * @param PhpArrayKeyExtractor $extractor Parameter extractor.
     * @param bool $checkempty Parameter checkempty.
     * @param array<string> $ignoredChecks Validation checks to ignore.
     */
    public function __construct(
        string $pluginroot,
        string $component,
        string $language,
        LanguageCatalog $catalog,
        PhpArrayKeyExtractor $extractor,
        bool $checkempty = true,
        array $ignoredChecks = [],
    ) {
        $this->pluginroot = $pluginroot;
        $this->component = $component;
        $this->language = $language;
        $this->catalog = $catalog;
        $this->extractor = $extractor;
        $this->checkempty = $checkempty;
        $this->ignoredChecks = $ignoredChecks;
    }

    /**
     * Checks whether a granular validation has been disabled.
     *
     * @param string $check Validation check identifier.
     * @return bool
     */
    public function ignores(string $check): bool {
        return in_array($check, $this->ignoredChecks, true);
    }

    /**
     * Method relative.
     *
     * @param string $file Parameter file.
     * @return string Return value.
     */
    public function relative(string $file): string {
        $root = rtrim(str_replace('\\', '/', realpath($this->pluginroot) ?: $this->pluginroot), '/');
        $path = str_replace('\\', '/', realpath($file) ?: $file);
        if (str_starts_with($path, $root . '/')) {
            return substr($path, strlen($root) + 1);
        }
        return $path;
    }

    /**
     * Method checkRequiredString.
     *
     * @param string $rule Parameter rule.
     * @param string $key Parameter key.
     * @param string $sourcefile Parameter sourcefile.
     * @param int $sourceline Parameter sourceline.
     * @param string $reason Parameter reason.
     * @return Check Return value.
     */
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
                null,
                true,
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
                null,
                true,
            );
        }

        return new Check(
            true,
            $rule,
            $this->relative($sourcefile),
            $sourceline,
            $key,
            "Language string \$string['{$key}'] exists.",
            null,
            true,
        );
    }

    /**
     * Method issueForRequiredString.
     *
     * @param string $rule Parameter rule.
     * @param string $key Parameter key.
     * @param string $sourcefile Parameter sourcefile.
     * @param int $sourceline Parameter sourceline.
     * @param string $reason Parameter reason.
     * @return ?Issue Return value.
     */
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
