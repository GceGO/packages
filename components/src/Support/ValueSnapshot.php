<?php

declare(strict_types=1);

namespace Gcego\Components\Support;

/**
 * Detaches PHP array references without cloning opt-in object values.
 * Call only after validating the value's types and nesting depth.
 */
final class ValueSnapshot
{
    public static function copy(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $snapshot = [];

        foreach ($value as $key => $item) {
            $snapshot[$key] = self::copy($item);
        }

        return $snapshot;
    }
}
