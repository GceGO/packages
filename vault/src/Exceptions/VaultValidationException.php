<?php

declare(strict_types=1);

namespace Gcego\Vault\Exceptions;

use RuntimeException;

/**
 * A change Vault refuses because it is invalid (unknown role, malformed
 * name, missing dependency, circular inheritance...). The message is safe to
 * show to the person who made the change; field names the input it concerns.
 */
class VaultValidationException extends RuntimeException implements VaultException
{
    public function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    public static function make(string $key, array $replace = [], ?string $field = null): static
    {
        return new static((string) __('gcego-vault::messages.errors.'.$key, $replace), $field); // @phpstan-ignore new.static
    }
}
