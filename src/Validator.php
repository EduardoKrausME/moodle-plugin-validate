<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate;

use EduardoKraus\MoodleStringValidate\Rule\AccessRule;
use EduardoKraus\MoodleStringValidate\Rule\CacheRule;
use EduardoKraus\MoodleStringValidate\Rule\MessageProviderRule;
use EduardoKraus\MoodleStringValidate\Rule\PluginNameRule;
use EduardoKraus\MoodleStringValidate\Rule\RuleInterface;
use EduardoKraus\MoodleStringValidate\Rule\SubpluginRule;
use RuntimeException;

final class Validator {
    /** @var RuleInterface[] */
    private array $rules;

    /** @param RuleInterface[]|null $rules */
    public function __construct(?array $rules = null) {
        $this->rules = $rules ?? [
            new PluginNameRule(),
            new SubpluginRule(),
            new AccessRule(),
            new MessageProviderRule(),
            new CacheRule(),
        ];
    }

    /** @return Issue[] */
    public function validate(string $pluginroot, string $language = 'en', bool $checkempty = true): array {
        $realroot = realpath($pluginroot);
        if ($realroot === false || !is_dir($realroot)) {
            throw new RuntimeException("Plugin path does not exist: {$pluginroot}");
        }

        $component = (new ComponentResolver())->resolve($realroot);
        $languagefile = $realroot . '/lang/' . $language . '/' . $component . '.php';
        if (!is_file($languagefile)) {
            return [new Issue(
                'languagefile',
                'lang/' . $language . '/' . $component . '.php',
                1,
                '',
                "Missing base language file lang/{$language}/{$component}.php.",
            )];
        }

        $context = new ValidationContext(
            $realroot,
            $component,
            $language,
            new LanguageCatalog($languagefile),
            new PhpArrayKeyExtractor(),
            $checkempty,
        );

        $issues = [];
        foreach ($this->rules as $rule) {
            array_push($issues, ...$rule->validate($context));
        }

        return $issues;
    }
}
