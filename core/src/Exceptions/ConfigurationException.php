<?php

declare(strict_types=1);

namespace Gcego\Core\Exceptions;

use UnexpectedValueException;

/**
 * Thrown when config('gcego') contains invalid values.
 *
 * Messages name the offending keys and expected types, never the values.
 */
class ConfigurationException extends UnexpectedValueException implements GcegoException
{
    /**
     * @param  list<string>  $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(sprintf(
            "Invalid GCEGO configuration (config/gcego.php):\n- %s",
            implode("\n- ", $errors),
        ));
    }
}
