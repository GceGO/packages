<?php

declare(strict_types=1);

namespace Gcego\Analytics\Support;

use Gcego\Analytics\Exceptions\InvalidDefinitionException;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Property;

/**
 * gcego/components refuses named constructor arguments; analytics
 * definitions accept them and turn them into the same Property values:
 *
 *     new Metric(name: 'total_students', aggregate: 'count')
 *     ==  new Metric(new Property(Metric::Name, 'total_students'), new Property(Metric::Aggregate, 'count'))
 */
final class NamedArguments
{
    /**
     * @param  array<array-key, array<mixed>|bool|float|int|string|ComponentInterface|ConfigurationNode|Property|\UnitEnum|null>  $items
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
                throw InvalidDefinitionException::invalid(class_basename($class), sprintf('it has no "%s" argument (use %s).', $key, implode(', ', array_keys($map))));
            }

            if ($value !== null) {
                $named[] = new Property($map[$key], $value instanceof \BackedEnum ? $value->value : $value);
            }
        }

        return [...$positional, ...$named];
    }
}
