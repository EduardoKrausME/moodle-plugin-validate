<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\Issue;
use EduardoKraus\MoodleStringValidate\ValidationContext;

interface RuleInterface {
    public function name(): string;

    /** @return Issue[] */
    public function validate(ValidationContext $context): array;
}
