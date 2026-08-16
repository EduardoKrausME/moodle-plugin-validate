<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate;

use RuntimeException;

final class PhpPluginMetadata {
    private string $contents;

    public function __construct(private readonly string $file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$file}");
        }
        $this->contents = $contents;
    }

    public function component(): ?string {
        if (preg_match('/\$plugin->component\s*=\s*([\'\"])([a-z][a-z0-9_]+)\1\s*;/', $this->contents, $match)) {
            return $match[2];
        }
        return null;
    }

    public function version(): int|float|null {
        return $this->numericProperty('version');
    }

    public function requires(): int|float|null {
        return $this->numericProperty('requires');
    }

    /** @return array<string, int|float|string> */
    public function dependencies(): array {
        if (!preg_match('/\$plugin->dependencies\s*=\s*(\[|array\s*\()(.*?)(?:\]|\))\s*;/s', $this->contents, $match)) {
            return [];
        }

        $dependencies = [];
        if (preg_match_all(
            '/([\'\"])([a-z][a-z0-9_]+)\1\s*=>\s*(ANY_VERSION|[0-9]+(?:\.[0-9]+)?)/',
            $match[2],
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $dependency) {
                $value = $dependency[3];
                if ($value === 'ANY_VERSION') {
                    $dependencies[$dependency[2]] = 'ANY_VERSION';
                } elseif (str_contains($value, '.')) {
                    $dependencies[$dependency[2]] = (float) $value;
                } else {
                    $dependencies[$dependency[2]] = (int) $value;
                }
            }
        }

        return $dependencies;
    }

    public function hasDependenciesDeclaration(): bool {
        return (bool) preg_match('/\$plugin->dependencies\s*=/', $this->contents);
    }

    public function lineFor(string $needle): int {
        $position = strpos($this->contents, $needle);
        if ($position === false) {
            return 1;
        }
        return substr_count(substr($this->contents, 0, $position), "\n") + 1;
    }

    private function numericProperty(string $property): int|float|null {
        if (!preg_match('/\$plugin->' . preg_quote($property, '/') . '\s*=\s*([0-9]+(?:\.[0-9]+)?)\s*;/', $this->contents, $match)) {
            return null;
        }
        return str_contains($match[1], '.') ? (float) $match[1] : (int) $match[1];
    }
}
