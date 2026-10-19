<?php

declare(strict_types=1);

namespace Gcego\Analytics\Support;

final class Keys
{
    public static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    public static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    public static function number(mixed $value): int|float|null
    {
        return match (true) {
            is_int($value), is_float($value) => $value,
            is_string($value) && is_numeric($value) => str_contains($value, '.') || str_contains(strtolower($value), 'e') ? (float) $value : (int) $value,
            is_bool($value) => (int) $value,
            default => null,
        };
    }
}
