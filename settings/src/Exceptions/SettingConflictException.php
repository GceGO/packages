<?php

declare(strict_types=1);

namespace Gcego\Settings\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Someone changed the setting after the caller read it (409): the write
 * carried an outdated version and was refused, never applied over the
 * newer value. Reload and try again.
 */
final class SettingConflictException extends ConflictHttpException implements SettingsException
{
    /**
     * @param  list<string>  $keys
     */
    private function __construct(string $message, public readonly array $keys)
    {
        parent::__construct($message);
    }

    /**
     * @param  list<string>  $keys
     */
    public static function for(array $keys): self
    {
        return new self(sprintf('The setting%s "%s" changed after you loaded %s. Reload to see the current value.', count($keys) > 1 ? 's' : '', implode('", "', $keys), count($keys) > 1 ? 'them' : 'it'), $keys);
    }
}
