<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate;

final class Issue {
    public function __construct(
        public readonly string $rule,
        public readonly string $file,
        public readonly int $line,
        public readonly string $key,
        public readonly string $message,
    ) {
    }
}
