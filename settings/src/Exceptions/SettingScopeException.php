<?php

declare(strict_types=1);

namespace Gcego\Settings\Exceptions;

use InvalidArgumentException;

/**
 * A scope that is disabled, unknown, not allowed for a setting, or not
 * resolvable from the current server context (no tenant, no panel, no user).
 */
final class SettingScopeException extends InvalidArgumentException implements SettingsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public static function notAllowed(string $key, string $scope): self
    {
        return new self(sprintf('The setting "%s" cannot be stored at the %s scope.', $key, $scope));
    }
}
