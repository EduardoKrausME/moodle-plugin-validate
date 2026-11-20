<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate;

/**
 * Class Issue.
 */
final class Issue {
    public string $rule;
    public string $file;
    public int $line;
    public string $key;
    public string $message;

    /**
     * Method __construct.
     *
     * @param string $rule Parameter rule.
     * @param string $file Parameter file.
     * @param int $line Parameter line.
     * @param string $key Parameter key.
     * @param string $message Parameter message.
     */
    public function __construct(
        string $rule,
        string $file,
        int $line,
        string $key,
        string $message
    ) {
        $this->rule = $rule;
        $this->file = $file;
        $this->line = $line;
        $this->key = $key;
        $this->message = $message;
    }
}
