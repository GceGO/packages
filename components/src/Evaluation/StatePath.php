<?php

declare(strict_types=1);

namespace Gcego\Components\Evaluation;

/**
 * Resolves Filament-style relative paths against a container path.
 *
 * "city" in container "data.address" is "data.address.city"; every leading
 * "../" moves one level up, so "../name" is "data.name".
 */
final class StatePath
{
    public static function resolve(string $containerPath, string $path, bool $isAbsolute = false): string
    {
        if ($isAbsolute) {
            return trim($path, '.');
        }

        $segments = $containerPath === '' ? [] : explode('.', $containerPath);

        while (str_starts_with($path, '../')) {
            array_pop($segments);
            $path = substr($path, 3);
        }

        if ($path === '..') {
            array_pop($segments);
            $path = '';
        }

        $path = trim($path, '.');

        if ($path !== '') {
            $segments[] = $path;
        }

        return implode('.', $segments);
    }
}
