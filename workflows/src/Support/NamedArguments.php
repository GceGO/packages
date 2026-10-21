<?php

declare(strict_types=1);

namespace Gcego\Workflows\Support;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Properties\Property;
use Gcego\Workflows\Exceptions\WorkflowDefinitionException;

/**
 * gcego/morfaw refuses named constructor arguments; workflow
 * definitions accept them as a convenience and turn them into the same
 * Property values (one internal representation):
 *
 *     new State(name: 'approved', label: 'Approved', terminal: true)
 *     ==  new State(new Property(State::Name, 'approved'), new Property(State::Label, 'Approved'), new Property(State::Terminal, true))
 */
final class NamedArguments
{
    /**
     * @param  array<array-key, array<mixed>|bool|float|int|string|ComponentInterface|ConfigurationNode|Property|\UnitEnum|null>  $items  Constructor arguments (positional first, then named).
     * @param  array<string, string>  $map  Argument name => property key.
     * @return list<array<mixed>|bool|float|int|string|ComponentInterface|ConfigurationNode|Property|\UnitEnum|null>
     */
    public static function normalize(string $class, array $items, array $map): array
    {
        $positional = [];
        $named = [];

        foreach ($items as $key => $value) {
            if (is_int($key)) {
                $positional[] = $value;

                continue;
            }

            if (! isset($map[$key])) {
                throw WorkflowDefinitionException::invalid(class_basename($class), sprintf('it has no "%s" argument (use %s).', $key, implode(', ', array_keys($map))));
            }

            if ($value !== null) {
                $named[] = new Property($map[$key], $value instanceof \BackedEnum ? $value->value : $value);
            }
        }

        return [...$positional, ...$named];
    }
}
