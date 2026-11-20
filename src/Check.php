<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate;

/**
 * Class Check.
 */
final class Check {
    public bool $ok;
    public string $rule;
    public string $file;
    public int $line;
    public string $key;
    public string $message;
    public bool $languageString;

    /**
     * Property severity.
     *
     * @var string
     */
    public string $severity;

    /**
     * Method __construct.
     *
     * @param bool $ok Parameter ok.
     * @param string $rule Parameter rule.
     * @param string $file Parameter file.
     * @param int $line Parameter line.
     * @param string $key Parameter key.
     * @param string $message Parameter message.
     * @param ?string $severity Parameter severity.
     * @param bool $languageString Parameter languageString.
     */
    public function __construct(
        bool $ok,
        string $rule,
        string $file,
        int $line,
        string $key,
        string $message,
        ?string $severity = null,
        bool $languageString = false,
    ) {
        $this->ok = $ok;
        $this->rule = $rule;
        $this->file = $file;
        $this->line = $line;
        $this->key = $key;
        $this->message = $message;
        $this->languageString = $languageString;
        $this->severity = $severity ?? ($ok ? 'ok' : 'error');
    }

    /**
     * Method warning.
     *
     * @param string $rule Parameter rule.
     * @param string $file Parameter file.
     * @param int $line Parameter line.
     * @param string $message Parameter message.
     * @param string $key Parameter key.
     * @return self Return value.
     */
    public static function warning(
        string $rule,
        string $file,
        int $line,
        string $message,
        string $key = '',
    ): self {
        return new self(true, $rule, $file, $line, $key, $message, 'warning');
    }

    /**
     * Method isError.
     *
     * @return bool Return value.
     */
    public function isError(): bool {
        return $this->severity === 'error';
    }

    /**
     * Method isWarning.
     *
     * @return bool Return value.
     */
    public function isWarning(): bool {
        return $this->severity === 'warning';
    }

    /**
     * Method target.
     *
     * @return string Return value.
     */
    public function target(): string {
        if ($this->key === '') {
            return $this->file;
        }

        if ($this->languageString) {
            return "\$string['{$this->key}']";
        }

        return $this->key;
    }

    /**
     * Method toIssue.
     *
     * @return ?Issue Return value.
     */
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
