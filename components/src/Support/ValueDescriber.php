<?php

declare(strict_types=1);

namespace Gcego\Components\Support;

use UnitEnum;

/**
 * Produces short, safe descriptions of values for error messages.
 *
 * Strings are truncated and arrays or objects are never dumped, so error
 * messages cannot leak large or sensitive payloads.
 */
final class ValueDescriber
{
    private const int MaxStringLength = 40;

    public static function describe(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'bool '.($value ? 'true' : 'false'),
            is_int($value) => 'int '.$value,
            is_float($value) => 'float '.var_export($value, true),
            is_string($value) => 'string "'.self::truncate($value).'"',
            is_array($value) => sprintf('array(%d)', count($value)),
            $value instanceof UnitEnum => 'enum '.$value::class.'::'.$value->name,
            default => self::type($value),
        };
    }

    public static function type(mixed $value): string
    {
        return get_debug_type($value);
    }

    private static function truncate(string $value): string
    {
        if (mb_strlen($value) <= self::MaxStringLength) {
            return $value;
        }

        return mb_substr($value, 0, self::MaxStringLength).'...';
    }
}
