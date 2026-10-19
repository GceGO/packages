<?php

declare(strict_types=1);

namespace Gcego\Settings\Validation;

use Gcego\Settings\Exceptions\InvalidSettingException;

/**
 * Typed values that passed, and translated errors per setting key.
 */
final readonly class SettingValidationResult
{
    /**
     * @param  array<string, mixed>  $values  Typed values of the keys that passed.
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(public array $values, public array $errors) {}

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /**
     * @throws InvalidSettingException
     */
    public function throwIfFailed(): void
    {
        if ($this->errors !== []) {
            throw InvalidSettingException::values($this->errors);
        }
    }
}
