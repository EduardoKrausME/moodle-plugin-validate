<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\ValidationContext;

final class MessageProviderRule implements RuleInterface {
    public function name(): string {
        return 'messageprovider';
    }

    public function validate(ValidationContext $context): array {
        $file = $context->pluginroot . '/db/messages.php';
        if (!is_file($file)) {
            return [];
        }

        $issues = [];
        foreach ($context->extractor->extract($file, 'messageproviders') as $provider) {
            $key = 'messageprovider:' . $provider['key'];
            $issue = $context->issueForRequiredString(
                $this->name(),
                $key,
                $file,
                $provider['line'],
                "required by message provider '{$provider['key']}' in db/messages.php.",
            );
            if ($issue !== null) {
                $issues[] = $issue;
            }
        }

        return $issues;
    }
}
