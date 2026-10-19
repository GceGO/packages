<?php

declare(strict_types=1);

namespace Gcego\Components\Exceptions;

use BadMethodCallException;
use Gcego\Components\Support\ValueDescriber;

/**
 * Thrown when a fluent builder is called with a method or value its
 * component cannot accept.
 */
class InvalidBuilderException extends BadMethodCallException implements ComponentException
{
    public static function notAComponent(string $class): self
    {
        return new self(sprintf('%s is not a GCEGO component class, so it cannot be built.', $class));
    }

    public static function unknownMethod(string $componentClass, string $method): self
    {
        return new self(sprintf(
            '%s has no "%s" setting. Fluent methods map to the component\'s property constants (%s::%s), or to methods of its builder.',
            self::basename($componentClass),
            $method,
            self::basename($componentClass),
            ucfirst($method),
        ));
    }

    public static function missingValue(string $componentClass, string $method): self
    {
        return new self(sprintf('%s::%s() needs a value: only on/off settings may be called without one.', self::basename($componentClass), $method));
    }

    public static function tooManyValues(string $componentClass, string $method): self
    {
        return new self(sprintf('%s::%s() takes a single value.', self::basename($componentClass), $method));
    }

    public static function unsupportedItem(string $componentClass, mixed $item): self
    {
        return new self(sprintf(
            '%s cannot hold %s. Pass properties, child components (or their builders) and configuration nodes.',
            self::basename($componentClass),
            ValueDescriber::describe($item),
        ));
    }

    public static function positionalAfterItem(string $componentClass, mixed $value): self
    {
        return new self(sprintf(
            '%s: the positional value %s must come before named settings, children and configuration nodes.',
            self::basename($componentClass),
            ValueDescriber::describe($value),
        ));
    }

    public static function tooManyPositional(string $componentClass, mixed $value): self
    {
        return new self(sprintf('%s::define() does not accept the extra positional value %s.', self::basename($componentClass), ValueDescriber::describe($value)));
    }

    public static function invalidValue(string $componentClass, string $method, string $reason): self
    {
        return new self(sprintf('%s::%s(): %s', self::basename($componentClass), $method, $reason));
    }

    private static function basename(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}
