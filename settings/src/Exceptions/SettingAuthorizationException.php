<?php

declare(strict_types=1);

namespace Gcego\Settings\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The actor may not do this with this setting at this scope (403). The
 * message names the operation and the setting, never its value.
 */
final class SettingAuthorizationException extends AccessDeniedHttpException implements SettingsException
{
    public static function for(string $operation, ?string $key = null): self
    {
        return new self($key === null ? sprintf('You may not %s these settings.', $operation) : sprintf('You may not %s the setting "%s".', $operation, $key));
    }
}
