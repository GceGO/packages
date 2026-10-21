<?php

declare(strict_types=1);

namespace Gcego\Components\Support;

use UnitEnum;

/**
 * Produces short, safe descriptions of values for error messages.
 *
 * Strings are truncated and array contents or object state are never dumped.
 * Ordinary string values remain visible in developer-facing diagnostics.
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

    /**
     * Anonymous class identifiers include a NUL followed by their source path.
     * Public metadata keeps only the class label, never that deployment path.
     */
    public static function className(string $class): string
    {
        return explode("\0", $class, 2)[0];
    }

    private static function truncate(string $value): string
    {
        $matched = preg_match('/\A(.{'.self::MaxStringLength.'})./us', $value, $matches);

        if ($matched === 1) {
            return $matches[1].'...';
        }

        // Invalid UTF-8 still needs a usable diagnostic, without extensions.
        return $matched === false && strlen($value) > self::MaxStringLength
            ? substr($value, 0, self::MaxStringLength).'...'
            : $value;
    }
}
