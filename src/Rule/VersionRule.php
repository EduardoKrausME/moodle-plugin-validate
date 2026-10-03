<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\PhpPluginMetadata;
use EduardoKraus\MoodleStringValidate\ValidationContext;

final class VersionRule implements RuleInterface {
    public function name(): string {
        return 'version';
    }

    public function validate(ValidationContext $context): array {
        $file = $context->pluginroot . '/version.php';
        if (!is_file($file)) {
            return [new Check(false, $this->name(), 'version.php', 1, '', 'Missing required version.php in project root.')];
        }

        $source = file_get_contents($file);
        if ($source === false) {
            return [new Check(false, $this->name(), 'version.php', 1, '', 'Unable to read version.php.')];
        }

        $metadata = new PhpPluginMetadata($file);
        $checks = [new Check(true, $this->name(), 'version.php', 1, '', 'version.php exists in project root.')];

        $assignments = $this->pluginAssignments($source);

        $release = $this->firstAssignment($assignments, 'release');
        if ($release === null) {
            $checks[] = new Check(
                false,
                $this->name(),
                'version.php',
                1,
                '$plugin->release',
                'Missing required $plugin->release in version.php.',
            );
        } elseif (($assignments[0]['name'] ?? null) !== 'release') {
            $checks[] = new Check(
                false,
                $this->name(),
                'version.php',
                $release['line'],
                '$plugin->release',
                '$plugin->release must be the first $plugin property assigned in version.php.',
            );
        } else {
            $checks[] = new Check(
                true,
                $this->name(),
                'version.php',
                $release['line'],
                '$plugin->release',
                '$plugin->release is the first plugin property.',
            );
        }

        $versionassignment = $this->firstAssignment($assignments, 'version');
        if ($versionassignment !== null) {
            if (($assignments[1]['name'] ?? null) !== 'version') {
                $checks[] = new Check(
                    false,
                    $this->name(),
                    'version.php',
                    $versionassignment['line'],
                    '$plugin->version',
                    '$plugin->version must be the second $plugin property assigned in version.php.',
                );
            } else {
                $checks[] = new Check(
                    true,
                    $this->name(),
                    'version.php',
                    $versionassignment['line'],
                    '$plugin->version',
                    '$plugin->version is the second plugin property.',
                );
            }
        }

        foreach ($assignments as $assignment) {
            if ($assignment['name'] === 'supported') {
                $checks[] = new Check(
                    false,
                    $this->name(),
                    'version.php',
                    $assignment['line'],
                    '$plugin->supported',
                    'Do not declare $plugin->supported in version.php. '
                        . 'This project does not use Moodle-version range arrays such as [405, 505].',
                );
            }

            if (
                $assignment['name'] === 'requires'
                && $this->isArrayExpression($assignment['value'])
            ) {
                $checks[] = new Check(
                    false,
                    $this->name(),
                    'version.php',
                    $assignment['line'],
                    '$plugin->requires',
                    '$plugin->requires must be a scalar numeric Moodle version, never an array.',
                );
            }
        }

        $component = $metadata->component();
        if ($component === null) {
            $checks[] = new Check(false, $this->name(), 'version.php', 1, '', 'Missing $plugin->component in version.php.');
        } elseif ($component !== $context->component) {
            $checks[] = new Check(
                false,
                $this->name(),
                'version.php',
                $metadata->lineFor('$plugin->component'),
                '',
                "Invalid \$plugin->component '{$component}'. Expected '{$context->component}'.",
            );
        } else {
            $checks[] = new Check(
                true,
                $this->name(),
                'version.php',
                $metadata->lineFor('$plugin->component'),
                '',
                "Plugin component is correctly configured as '{$component}'.",
            );
        }

        $version = $metadata->version();
        if ($version === null || $version <= 0) {
            $checks[] = new Check(false, $this->name(), 'version.php', 1, '', 'Missing or invalid numeric $plugin->version in version.php.');
        } else {
            $checks[] = new Check(
                true,
                $this->name(),
                'version.php',
                $metadata->lineFor('$plugin->version'),
                '',
                "Plugin version is configured: {$version}.",
            );
        }

        return $checks;
    }

    /**
     * @return array<int, array{name: string, value: string, line: int}>
     */
    private function pluginAssignments(string $source): array {
        preg_match_all(
            '/^\s*\$plugin->([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.+?);\s*(?:\/\/.*|#.*)?$/m',
            $source,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );

        $assignments = [];
        foreach ($matches as $match) {
            $offset = $match[0][1];
            $assignments[] = [
                'name' => $match[1][0],
                'value' => trim($match[2][0]),
                'line' => substr_count(substr($source, 0, $offset), "\n") + 1,
            ];
        }

        return $assignments;
    }

    /**
     * @param array<int, array{name: string, value: string, line: int}> $assignments
     * @return array{name: string, value: string, line: int}|null
     */
    private function firstAssignment(array $assignments, string $name): ?array {
        foreach ($assignments as $assignment) {
            if ($assignment['name'] === $name) {
                return $assignment;
            }
        }

        return null;
    }

    private function isArrayExpression(string $value): bool {
        $value = ltrim($value);
        return str_starts_with($value, '[') || preg_match('/^array\s*\(/i', $value) === 1;
    }
}
