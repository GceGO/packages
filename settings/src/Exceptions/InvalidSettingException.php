<?php

declare(strict_types=1);

namespace Gcego\Settings\Exceptions;

use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * A setting definition or configuration is invalid, or submitted values
 * failed casting or validation (errors(): setting key => messages).
 */
final class InvalidSettingException extends InvalidArgumentException implements SettingsException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    private function __construct(string $message, private readonly array $errors = [], public readonly ?string $reason = null)
    {
        parent::__construct($message);
    }

    /**
     * A value that cannot be converted to its type ($reason: a code such as
     * "integer", translated as gcego-settings::messages.cast.<code>).
     */
    public static function cast(string $reason): self
    {
        return new self(sprintf('The value cannot be used (%s).', $reason), [], $reason);
    }

    public static function definition(string $subject, string $reason): self
    {
        return new self(sprintf('%s is invalid: %s', $subject, $reason));
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function values(array $errors): self
    {
        $first = array_key_first($errors);

        return new self($first === null ? 'The settings are invalid.' : sprintf('The setting "%s" is invalid: %s', $first, $errors[$first][0] ?? 'invalid value.'), $errors);
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function toValidationException(): ValidationException
    {
        return ValidationException::withMessages($this->errors === [] ? ['settings' => [$this->getMessage()]] : $this->errors);
    }
}
