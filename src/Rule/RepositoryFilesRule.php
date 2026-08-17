<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\ValidationContext;

final class RepositoryFilesRule implements RuleInterface {
    public function name(): string {
        return 'repository';
    }

    public function validate(ValidationContext $context): array {
        return [
            $this->checkOneOf(
                $context,
                ['LICENSE', 'LICENSE.md', 'LICENSE.txt', 'COPYING', 'COPYING.txt'],
                'license',
                'Missing license file in the project root. Expected LICENSE, LICENSE.md, LICENSE.txt, COPYING, or COPYING.txt.'
            ),
            $this->checkOneOf(
                $context,
                ['README.md'],
                'README',
                'Missing README file in the project root. Expected README.md, README, README.txt, or README.rst.'
            ),
        ];
    }

    private function checkOneOf(
        ValidationContext $context,
        array $filenames,
        string $label,
        string $missingmessage,
    ): Check {
        foreach ($filenames as $filename) {
            $file = $context->pluginroot . '/' . $filename;
            if (is_file($file)) {
                return new Check(
                    true,
                    $this->name(),
                    $filename,
                    1,
                    '',
                    "{$label} file exists in project root: {$filename}.",
                );
            }
        }

        return new Check(false, $this->name(), $filenames[0], 1, '', $missingmessage);
    }
}
