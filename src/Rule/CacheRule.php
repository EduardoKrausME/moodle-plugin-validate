<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\ValidationContext;

final class CacheRule implements RuleInterface {
    public function name(): string {
        return 'cache';
    }

    public function validate(ValidationContext $context): array {
        $file = $context->pluginroot . '/db/caches.php';
        if (!is_file($file)) {
            return [];
        }

        $issues = [];
        foreach ($context->extractor->extract($file, 'definitions') as $definition) {
            $key = 'cachedef_' . $definition['key'];
            $issue = $context->issueForRequiredString(
                $this->name(),
                $key,
                $file,
                $definition['line'],
                "required by cache definition '{$definition['key']}' in db/caches.php.",
            );
            if ($issue !== null) {
                $issues[] = $issue;
            }
        }

        return $issues;
    }
}
