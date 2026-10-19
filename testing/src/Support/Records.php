<?php

declare(strict_types=1);

namespace Gcego\Testing\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Reads keys and attributes from the records a query returned: Eloquent
 * models, arrays or plain objects.
 */
final class Records
{
    public static function key(mixed $record, ?string $keyName = null): string|int|null
    {
        if ($record instanceof Model && $keyName === null) {
            $key = $record->getKey();

            return is_int($key) || is_string($key) ? $key : null;
        }

        $value = self::value($record, $keyName ?? 'id');

        return is_int($value) || is_string($value) ? $value : null;
    }

    public static function value(mixed $record, string $field): mixed
    {
        return data_get($record, $field);
    }

    /**
     * @param  iterable<mixed>  $records
     * @return list<string>
     */
    public static function keys(iterable $records, ?string $keyName = null): array
    {
        $keys = [];

        foreach ($records as $record) {
            $keys[] = (string) self::key($record, $keyName);
        }

        return $keys;
    }

    /**
     * @param  iterable<mixed>  $records
     * @return list<mixed>
     */
    public static function pluck(iterable $records, string $field): array
    {
        $values = [];

        foreach ($records as $record) {
            $values[] = self::value($record, $field);
        }

        return $values;
    }

    /**
     * Keys as strings, so 1 and "1" compare equal.
     *
     * @param  iterable<string|int|Model>  $keys
     * @return list<string>
     */
    public static function normalize(iterable $keys): array
    {
        $normalized = [];

        foreach ($keys as $key) {
            $normalized[] = $key instanceof Model ? (string) self::key($key) : (string) $key;
        }

        return $normalized;
    }
}
