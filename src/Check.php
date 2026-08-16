<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate;

final class Check {
    public readonly string $severity;

    public function __construct(
        public readonly bool $ok,
        public readonly string $rule,
        public readonly string $file,
        public readonly int $line,
        public readonly string $key,
        public readonly string $message,
        ?string $severity = null,
    ) {
        $this->severity = $severity ?? ($ok ? 'ok' : 'error');
    }

    public static function warning(
        string $rule,
        string $file,
        int $line,
        string $message,
        string $key = '',
    ): self {
        return new self(true, $rule, $file, $line, $key, $message, 'warning');
    }

    public function isError(): bool {
        return $this->severity === 'error';
    }

    public function isWarning(): bool {
        return $this->severity === 'warning';
    }

    public function toIssue(): ?Issue {
        if (!$this->isError()) {
            return null;
        }

        return new Issue(
            $this->rule,
            $this->file,
            $this->line,
            $this->key,
            $this->message,
        );
    }
}
