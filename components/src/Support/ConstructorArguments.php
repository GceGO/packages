<?php

declare(strict_types=1);

namespace Gcego\Components\Support;

use Gcego\Components\Contracts\Buildable;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Property;

/**
 * Classifies component constructor arguments.
 *
 * Rules, applied in order:
 *  1. Named arguments are rejected.
 *  2. Leading arguments that are not Property, ComponentInterface or
 *     ConfigurationNode instances are shorthand values. Each is mapped to the
 *     next key declared by the component's shorthand list and becomes an
 *     ordinary Property. Extra shorthand values are rejected.
 *  3. Once the first Property, child or configuration node appears, every
 *     remaining argument must be one of those three kinds.
 *
 * Builders (Buildable) are built and treated as the child they produce.
 *
 * Classification depends only on contracts, never on namespaces or naming.
 */
final class ConstructorArguments
{
    /**
     * @param  class-string  $componentClass
     * @param  list<string>  $shorthandKeys
     * @param  array<int|string, mixed>  $arguments
     */
    public static function parse(string $componentClass, array $shorthandKeys, array $arguments): ParsedArguments
    {
        $properties = [];
        $children = [];
        $configurations = [];
        $acceptsShorthand = true;
        $position = 0;

        foreach ($arguments as $name => $argument) {
            if (is_string($name)) {
                throw InvalidConfigurationException::namedArgument($componentClass, $name);
            }

            if ($argument instanceof Buildable) {
                $argument = $argument->build();
            }

            if ($argument instanceof Property) {
                $properties[] = $argument;
                $acceptsShorthand = false;
            } elseif ($argument instanceof ComponentInterface) {
                $children[] = $argument;
                $acceptsShorthand = false;
            } elseif ($argument instanceof ConfigurationNode) {
                $configurations[] = $argument;
                $acceptsShorthand = false;
            } elseif (! $acceptsShorthand) {
                throw InvalidConfigurationException::shorthandAfterItem($componentClass, $position, $argument);
            } elseif (! array_key_exists($position, $shorthandKeys)) {
                throw InvalidConfigurationException::unsupportedArgument($componentClass, $position, $argument, $shorthandKeys);
            } else {
                $properties[] = new Property($shorthandKeys[$position], $argument);
            }

            $position++;
        }

        return new ParsedArguments($properties, $children, $configurations);
    }
}
