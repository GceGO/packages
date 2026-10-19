<?php

declare(strict_types=1);

namespace Gcego\Components;

use Gcego\Components\Contracts\RuntimeValue;
use Gcego\Components\Contracts\ValueObject;
use Gcego\Components\Exceptions\InvalidPropertyException;
use Gcego\Components\Support\ValueDescriber;
use UnitEnum;

/**
 * One immutable configuration value of a component.
 *
 * Supported values: null, scalars, arrays of supported values (nested at most
 * 32 levels), enums, ValueObject and RuntimeValue instances. Everything else,
 * including closures and arbitrary objects, is rejected.
 */
final readonly class Property
{
    public const string KeyPattern = '/^[A-Za-z_][A-Za-z0-9_.\-]{0,127}$/';

    private const int MaxArrayDepth = 32;

    public function __construct(
        public string $key,
        public mixed $value = null,
    ) {
        if (preg_match(self::KeyPattern, $key) !== 1) {
            throw InvalidPropertyException::invalidKey($key);
        }

        $unsupported = self::findUnsupportedValue($value, 'value', 0);

        if ($unsupported !== null) {
            throw InvalidPropertyException::unsupportedValue($key, $unsupported[0], $unsupported[1]);
        }
    }

    public static function isSupportedValue(mixed $value): bool
    {
        return self::findUnsupportedValue($value, 'value', 0) === null;
    }

    /**
     * @return array{0: string, 1: string}|null The location and type of the first unsupported value.
     */
    private static function findUnsupportedValue(mixed $value, string $location, int $depth): ?array
    {
        if ($value === null
            || is_scalar($value)
            || $value instanceof UnitEnum
            || $value instanceof ValueObject
            || $value instanceof RuntimeValue) {
            return null;
        }

        if (! is_array($value)) {
            return [$location, ValueDescriber::type($value)];
        }

        if ($depth >= self::MaxArrayDepth) {
            return [$location, sprintf('an array nesting deeper than %d levels', self::MaxArrayDepth)];
        }

        foreach ($value as $index => $item) {
            $unsupported = self::findUnsupportedValue($item, $location.'['.$index.']', $depth + 1);

            if ($unsupported !== null) {
                return $unsupported;
            }
        }

        return null;
    }
}
