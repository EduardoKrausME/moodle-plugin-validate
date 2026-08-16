<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\ValidationContext;

interface RuleInterface {
    public function name(): string;

    /** @return Check[] */
    public function validate(ValidationContext $context): array;
}
