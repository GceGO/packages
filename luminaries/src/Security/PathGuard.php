<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Security;

use Gcego\Luminaries\Exceptions\UnsafeSvgException;
use Gcego\Luminaries\IconName;

/**
 * Filesystem rules for custom icon collections: a collection directory must
 * resolve (after symlinks) inside one of the configured trusted roots, and an
 * icon file must be a regular ".svg" file directly inside its collection
 * directory. Icon names are checked against the identifier grammar first, so
 * "../", absolute paths and null bytes never reach the filesystem.
 */
final readonly class PathGuard
{
    /**
     * @param  list<string>  $trustedRoots
     */
    public function __construct(private array $trustedRoots) {}

    /**
     * The real path of a collection directory.
     *
     * @throws UnsafeSvgException
     */
    public function directory(string $collection, string $path): string
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw UnsafeSvgException::directory($collection, $path, 'the path is empty or invalid');
        }

        $real = realpath($path);

        if ($real === false || ! is_dir($real)) {
            throw UnsafeSvgException::directory($collection, $path, 'the directory does not exist');
        }

        foreach ($this->trustedRoots as $root) {
            $rootReal = realpath($root);

            if ($rootReal !== false && ($real === $rootReal || str_starts_with($real, rtrim($rootReal, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR))) {
                return $real;
            }
        }

        throw UnsafeSvgException::directory($collection, $path, 'the directory is outside the trusted roots (luminaries.security.trusted_roots)');
    }

    /**
     * The real path of an icon file inside a resolved collection directory, or null when there is no such safe file.
     */
    public function file(string $directory, string $icon): ?string
    {
        if (preg_match(IconName::IconPattern, $icon) !== 1) {
            return null;
        }

        $candidate = $directory.DIRECTORY_SEPARATOR.$icon.'.svg';

        if (! is_file($candidate)) {
            return null;
        }

        $real = realpath($candidate);

        if ($real === false || dirname($real) !== $directory || ! str_ends_with($real, '.svg')) {
            return null;
        }

        return $real;
    }
}
