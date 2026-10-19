<?php

declare(strict_types=1);

namespace Gcego\Workflows\Support;

final class Keys
{
    public static function string(mixed $key): string
    {
        return is_scalar($key) ? (string) $key : '';
    }

    public static function int(mixed $key): int
    {
        return is_numeric($key) ? (int) $key : 0;
    }
}
