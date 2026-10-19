<?php

declare(strict_types=1);

namespace Gcego\Settings\Support;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Property;
use Gcego\Settings\Exceptions\InvalidSettingException;

/**
 * gcego/components refuses named constructor arguments; settings
 * definitions accept them as a convenience and turn them into the same
 * Property values (one internal representation):
 *
 *     new Setting(name: 'site_name', label: 'Website Name', default: 'GCEGO')
 *     ==  new Setting(new Property(Setting::Name, 'site_name'), new Property(Setting::Label, 'Website Name'), new Property(Setting::Default, 'GCEGO'))
 *
 * A named "default: null" is kept (an explicit null default); other named
 * nulls mean "not given". Backed enum arguments become their backing value.
 */
final class NamedArguments
{
    /**
     * @param  array<array-key, array<mixed>|bool|float|int|string|ComponentInterface|ConfigurationNode|Property|\UnitEnum|null>  $items  Constructor arguments (positional first, then named).
     * @param  array<string, string>  $map  Argument name => property key.
     * @param  list<string>  $keepNull  Argument names whose null is meaningful.
     * @return list<array<mixed>|bool|float|int|string|ComponentInterface|ConfigurationNode|Property|\UnitEnum|null>
     */
    public static function normalize(string $class, array $items, array $map, array $keepNull = []): array
    {
        $positional = [];
        $named = [];

        foreach ($items as $key => $value) {
            if (is_int($key)) {
                $positional[] = $value;

                continue;
            }

            if (! isset($map[$key])) {
                throw InvalidSettingException::definition(class_basename($class), sprintf('it has no "%s" argument (use %s).', $key, implode(', ', array_keys($map))));
            }

            if ($value instanceof ComponentInterface || $value instanceof ConfigurationNode || $value instanceof Property) {
                throw InvalidSettingException::definition(class_basename($class), sprintf('pass "%s" as a value, not as a component.', $key));
            }

            if ($value !== null || in_array($key, $keepNull, true)) {
                $named[] = new Property($map[$key], $value instanceof \BackedEnum ? $value->value : $value);
            }
        }

        return [...$positional, ...$named];
    }
}
