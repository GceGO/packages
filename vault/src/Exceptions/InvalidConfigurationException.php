<?php

declare(strict_types=1);

namespace Gcego\Vault\Exceptions;

use LogicException;

/**
 * Unsafe or invalid gcego-vault configuration, rejected at boot.
 */
final class InvalidConfigurationException extends LogicException implements VaultException
{
    public static function because(string $reason): self
    {
        return new self('Invalid gcego-vault configuration: '.$reason);
    }
}
