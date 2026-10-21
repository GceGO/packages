<?php

declare(strict_types=1);

namespace Gcego\Components\Exceptions;

use Gcego\Components\Properties\Property;
use InvalidArgumentException;

/**
 * Thrown when a Property is constructed with an invalid key or a value type the
 * component engine cannot store safely.
 */
class InvalidPropertyException extends InvalidArgumentException implements ComponentException
{
    public static function invalidKey(string $key): self
    {
        return new self(sprintf(
            'Invalid property key "%s". Keys must match %s (a letter or underscore followed by letters, digits, "_", "." or "-", at most 128 characters).',
            $key,
            Property::KeyPattern,
        ));
    }

    public static function unsupportedValue(string $key, string $location, string $actualType): self
    {
        $hint = match (true) {
            $actualType === 'Closure' => ' Wrap runtime callables in an object implementing Gcego\Components\Contracts\RuntimeValue.',
            str_contains($actualType, 'nesting') => '',
            default => ' Use a scalar, array, enum, or an immutable object implementing Gcego\Components\Contracts\ValueObject. Child components must be passed as constructor items, not property values.',
        };

        return new self(sprintf(
            'Unsupported value for property "%s": %s is %s.%s',
            $key,
            $location,
            $actualType,
            $hint,
        ));
    }
}
