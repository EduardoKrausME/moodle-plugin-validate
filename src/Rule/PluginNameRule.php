<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\ValidationContext;

final class PluginNameRule implements RuleInterface {
    public function name(): string {
        return 'pluginname';
    }

    public function validate(ValidationContext $context): array {
        $source = is_file($context->pluginroot . '/version.php')
            ? $context->pluginroot . '/version.php'
            : $context->catalog->file();

        $issue = $context->issueForRequiredString(
            $this->name(),
            'pluginname',
            $source,
            1,
            'every Moodle plugin must define its display name in the base language file.',
        );

        return $issue === null ? [] : [$issue];
    }
}
