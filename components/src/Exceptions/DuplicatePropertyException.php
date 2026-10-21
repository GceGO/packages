<?php

declare(strict_types=1);

namespace Gcego\Components\Exceptions;

use Gcego\Components\Properties\Property;
use Gcego\Components\Support\ValueDescriber;
use Throwable;

/**
 * Thrown when the same property key is declared more than once for a single
 * component, including a shorthand value that collides with an explicit Property.
 */
class DuplicatePropertyException extends InvalidPropertyException
{
    public static function forKey(string $key, ?string $componentClass = null, ?Throwable $previous = null): self
    {
        return new self(
            $componentClass === null
                ? sprintf('Property "%s" is declared more than once.', $key)
                : sprintf('Property "%s" is declared more than once on %s. Each property may only be declared once per component.', $key, ValueDescriber::className($componentClass)),
            0,
            $previous,
        );
    }
}
