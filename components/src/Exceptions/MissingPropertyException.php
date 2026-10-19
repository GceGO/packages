<?php

declare(strict_types=1);

namespace Gcego\Components\Exceptions;

use OutOfBoundsException;

/**
 * Thrown when a property value is required but neither an explicit value nor a
 * schema default exists.
 */
class MissingPropertyException extends OutOfBoundsException implements ComponentException
{
    public static function forKey(string $key, string $componentType, string $componentClass): self
    {
        return new self(sprintf(
            'Component "%s" (%s) has no value for property "%s" and its definition declares no default.',
            $componentType,
            $componentClass,
            $key,
        ));
    }
}
