<?php

declare(strict_types=1);

namespace Gcego\Media\Support;

final class Bytes
{
    public static function format(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = max(0, $bytes);
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return ($unit === 0 ? (string) $size : number_format($size, $size >= 10 ? 0 : 1)).' '.$units[$unit];
    }
}
