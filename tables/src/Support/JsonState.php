<?php

declare(strict_types=1);

namespace Gcego\Tables\Support;

use Gcego\Tables\Exceptions\InvalidTableStateException;
use JsonException;

/**
 * Bounded JSON for stored table state.
 */
final class JsonState
{
    public const int MaxBytes = 32768;

    /**
     * @param  array<string, mixed>  $state
     */
    public static function encode(array $state): string
    {
        try {
            $json = json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            throw InvalidTableStateException::invalid('state', 'cannot be stored.');
        }

        if (strlen($json) > self::MaxBytes) {
            throw InvalidTableStateException::invalid('state', 'is too large to store.');
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(mixed $json): array
    {
        if (! is_string($json) || strlen($json) > self::MaxBytes) {
            return [];
        }

        try {
            $state = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (! is_array($state)) {
            return [];
        }

        $clean = [];

        foreach ($state as $key => $value) {
            $clean[(string) $key] = $value;
        }

        return $clean;
    }
}
