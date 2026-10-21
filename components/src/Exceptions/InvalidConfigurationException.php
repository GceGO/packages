<?php

declare(strict_types=1);

namespace Gcego\Components\Exceptions;

use Gcego\Components\ComponentServiceProvider;
use Gcego\Components\Support\ValueDescriber;
use InvalidArgumentException;

/**
 * Thrown when a component is declared with constructor arguments it cannot
 * accept, or when a schema definition is internally inconsistent.
 */
class InvalidConfigurationException extends InvalidArgumentException implements ComponentException
{
    public static function inactiveManager(): self
    {
        return new self('ComponentManager belongs to an inactive application or scope. Resolve it again through the Components facade or service container.');
    }

    public static function managerNotSerializable(): self
    {
        return new self('ComponentManager cannot be serialized. Resolve it through the service provider in each application scope.');
    }

    public static function providerNotRegistered(): self
    {
        return new self(sprintf(
            'ComponentServiceProvider must be registered before using gcego/morfaw. Enable Laravel package discovery or register %s in bootstrap/providers.php and boot Laravel facades.',
            ComponentServiceProvider::class,
        ));
    }

    public static function namedArgument(string $componentClass, string $argumentName): self
    {
        $componentClass = ValueDescriber::className($componentClass);

        return new self(sprintf(
            'Named argument "%s" is not supported by %s. Declare configuration with new Property(%s::Constant, $value) instead.',
            $argumentName,
            $componentClass,
            self::basename($componentClass),
        ));
    }

    /**
     * @param  list<string>  $shorthandKeys
     */
    public static function unsupportedArgument(string $componentClass, int $position, mixed $value, array $shorthandKeys): self
    {
        $componentClass = ValueDescriber::className($componentClass);

        $accepted = $shorthandKeys === []
            ? sprintf('%s accepts no positional shorthand values', self::basename($componentClass))
            : sprintf(
                '%s accepts %d positional shorthand value(s): %s',
                self::basename($componentClass),
                count($shorthandKeys),
                implode(', ', $shorthandKeys),
            );

        return new self(sprintf(
            "Unsupported constructor argument #%d (%s) for %s.\n\n%s. Pass a Property, a child component or a configuration node instead.",
            $position + 1,
            ValueDescriber::describe($value),
            $componentClass,
            $accepted,
        ));
    }

    public static function shorthandAfterItem(string $componentClass, int $position, mixed $value): self
    {
        $componentClass = ValueDescriber::className($componentClass);

        return new self(sprintf(
            "Unsupported constructor argument #%d (%s) for %s.\n\nPositional shorthand values must precede every Property, child component and configuration node.",
            $position + 1,
            ValueDescriber::describe($value),
            $componentClass,
        ));
    }

    public static function invalidDefinition(string $reason): self
    {
        return new self('Invalid component definition: '.$reason);
    }

    public static function notNormalizable(string $location, mixed $value): self
    {
        return new self(sprintf(
            'Value at %s (%s) cannot be normalized. Only null, scalars, arrays, enums, ValueObject and RuntimeValue instances are supported.',
            $location,
            ValueDescriber::type($value),
        ));
    }

    private static function basename(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}
