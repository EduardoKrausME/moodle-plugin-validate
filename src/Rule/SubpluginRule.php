<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\ValidationContext;

final class SubpluginRule implements RuleInterface {
    public function name(): string {
        return 'subplugin';
    }

    public function validate(ValidationContext $context): array {
        $file = $context->pluginroot . '/db/subplugins.json';
        if (!is_file($file)) {
            return [];
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            return [new Check(false, $this->name(), 'db/subplugins.json', 1, '', 'Unable to read db/subplugins.json.')];
        }

        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return [new Check(
                false,
                $this->name(),
                'db/subplugins.json',
                1,
                '',
                'Invalid JSON in db/subplugins.json: ' . $exception->getMessage(),
            )];
        }

        $types = [];
        foreach (['plugintypes', 'subplugintypes'] as $section) {
            if (!isset($data[$section]) || !is_array($data[$section])) {
                continue;
            }
            foreach (array_keys($data[$section]) as $type) {
                if (is_string($type) && $type !== '') {
                    $types[$type] = $this->findJsonKeyLine($contents, $type);
                }
            }
        }

        $checks = [];
        foreach ($types as $type => $line) {
            foreach (['subplugintype_' . $type, 'subplugintype_' . $type . '_plural'] as $key) {
                $checks[] = $context->checkRequiredString(
                    $this->name(),
                    $key,
                    $file,
                    $line,
                    "required by subplugin type '{$type}' in db/subplugins.json.",
                );
            }
        }

        return $checks;
    }

    private function findJsonKeyLine(string $contents, string $key): int {
        $lines = preg_split('/\R/', $contents) ?: [];
        foreach ($lines as $index => $line) {
            if (preg_match('/[\"\']' . preg_quote($key, '/') . '[\"\']\s*:/', $line)) {
                return $index + 1;
            }
        }
        return 1;
    }
}
