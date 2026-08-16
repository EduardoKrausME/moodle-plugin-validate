<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate;

final class Check {
    public function __construct(
        public readonly bool $ok,
        public readonly string $rule,
        public readonly string $file,
        public readonly int $line,
        public readonly string $key,
        public readonly string $message,
    ) {
    }

    public function isError(): bool {
        return !$this->ok;
    }

    public function toIssue(): ?Issue {
        if ($this->ok) {
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
