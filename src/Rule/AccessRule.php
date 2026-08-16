<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\ValidationContext;

final class AccessRule implements RuleInterface {
    public function name(): string {
        return 'access';
    }

    public function validate(ValidationContext $context): array {
        $file = $context->pluginroot . '/db/access.php';
        if (!is_file($file)) {
            return [];
        }

        $checks = [];
        foreach ($context->extractor->extract($file, 'capabilities') as $capability) {
            $parts = preg_split('~[/:]~', $capability['key'], 3);
            if ($parts === false || count($parts) !== 3) {
                continue;
            }

            [, $pluginname, $capabilityname] = $parts;
            $key = $pluginname . ':' . $capabilityname;
            $checks[] = $context->checkRequiredString(
                $this->name(),
                $key,
                $file,
                $capability['line'],
                "required by capability '{$capability['key']}' in db/access.php.",
            );
        }

        return $checks;
    }
}
