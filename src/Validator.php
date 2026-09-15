<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate;

use EduardoKraus\MoodleStringValidate\Rule\AccessRule;
use EduardoKraus\MoodleStringValidate\Rule\CacheRule;
use EduardoKraus\MoodleStringValidate\Rule\DbReferencesRule;
use EduardoKraus\MoodleStringValidate\Rule\ExternalApiRule;
use EduardoKraus\MoodleStringValidate\Rule\GetStringRule;
use EduardoKraus\MoodleStringValidate\Rule\InstallXmlHeaderRule;
use EduardoKraus\MoodleStringValidate\Rule\InstallXmlRule;
use EduardoKraus\MoodleStringValidate\Rule\JavascriptHtmlRule;
use EduardoKraus\MoodleStringValidate\Rule\LegacyAjaxRule;
use EduardoKraus\MoodleStringValidate\Rule\MessageProviderRule;
use EduardoKraus\MoodleStringValidate\Rule\ModBackupRestoreRule;
use EduardoKraus\MoodleStringValidate\Rule\ModCourseContentsRule;
use EduardoKraus\MoodleStringValidate\Rule\ModSupportsRule;
use EduardoKraus\MoodleStringValidate\Rule\MoodleExceptionRule;
use EduardoKraus\MoodleStringValidate\Rule\MustacheUrlRule;
use EduardoKraus\MoodleStringValidate\Rule\PrivacyRule;
use EduardoKraus\MoodleStringValidate\Rule\PluginNameRule;
use EduardoKraus\MoodleStringValidate\Rule\RepositoryFilesRule;
use EduardoKraus\MoodleStringValidate\Rule\RuleInterface;
use EduardoKraus\MoodleStringValidate\Rule\SubpluginRule;
use EduardoKraus\MoodleStringValidate\Rule\TranslationPlaceholderRule;
use EduardoKraus\MoodleStringValidate\Rule\VersionRule;
use RuntimeException;

final class Validator {
    /** @var RuleInterface[] */
    private array $rules;

    /** @param RuleInterface[]|null $rules */
    public function __construct(?array $rules = null) {
        $this->rules = $rules ?? [
            new RepositoryFilesRule(),
            new VersionRule(),
            new PluginNameRule(),
            new InstallXmlHeaderRule(),
            new InstallXmlRule(),
            new ModCourseContentsRule(),
            new ModSupportsRule(),
            new ModBackupRestoreRule(),
            new DbReferencesRule(),
            new ExternalApiRule(),
            new SubpluginRule(),
            new AccessRule(),
            new MessageProviderRule(),
            new CacheRule(),
            new PrivacyRule(),
            new GetStringRule(),
            new TranslationPlaceholderRule(),
            new MoodleExceptionRule(),
            new LegacyAjaxRule(),
            new JavascriptHtmlRule(),
            new MustacheUrlRule(),
        ];
    }

    /** @return Check[] */
    public function validateDetailed(string $pluginroot, string $language = 'en', bool $checkempty = true): array {
        $realroot = realpath($pluginroot);
        if ($realroot === false || !is_dir($realroot)) {
            throw new RuntimeException("Plugin path does not exist: {$pluginroot}");
        }

        $component = (new ComponentResolver())->resolve($realroot);
        $languagecomponent = str_starts_with($component, 'mod_') ? substr($component, 4) : $component;
        $languagefile = $realroot . '/lang/' . $language . '/' . $languagecomponent . '.php';
        if (!is_file($languagefile)) {
            return [new Check(
                false,
                'languagefile',
                'lang/' . $language . '/' . $languagecomponent . '.php',
                1,
                '',
                "Missing base language file lang/{$language}/{$languagecomponent}.php.",
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

        $checks = [];
        foreach ($this->rules as $rule) {
            array_push($checks, ...$rule->validate($context));
        }
        return $checks;
    }

    /** @return Issue[] */
    public function validate(string $pluginroot, string $language = 'en', bool $checkempty = true): array {
        $issues = [];
        foreach ($this->validateDetailed($pluginroot, $language, $checkempty) as $check) {
            $issue = $check->toIssue();
            if ($issue !== null) {
                $issues[] = $issue;
            }
        }
        return $issues;
    }
}
