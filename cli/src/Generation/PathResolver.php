<?php

declare(strict_types=1);

namespace Gcego\Cli\Generation;

use Gcego\Cli\Exceptions\UnsafePathException;

/**
 * Resolves and vets every path the CLI writes.
 *
 * A target must lie inside the application's base path, contain no ".."
 * or NUL, and no existing part of it — inside the application — may be a
 * symbolic link (so a link cannot redirect a write elsewhere). Paths come
 * from configuration and validated names, never verbatim from options.
 */
final readonly class PathResolver
{
    public function __construct(private string $basePath) {}

    public function basePath(): string
    {
        return rtrim($this->basePath, '/');
    }

    /**
     * An absolute, normalized, writable-in-principle path inside the application.
     *
     * @throws UnsafePathException
     */
    public function resolve(string $path): string
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw UnsafePathException::because($path === '' ? '(empty path)' : 'a path', 'it is empty or contains a NUL byte.');
        }

        $base = $this->canonicalBase();
        $absolute = str_starts_with($path, '/') ? $path : $this->basePath().'/'.$path;
        $segments = [];

        foreach (explode('/', $absolute) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw UnsafePathException::because($this->relative($path), 'it contains "..".');
            }

            $segments[] = $segment;
        }

        $normalized = '/'.implode('/', $segments);
        $insideBase = $normalized === $this->basePath() || str_starts_with($normalized, $this->basePath().'/');
        $insideCanonical = $normalized === $base || str_starts_with($normalized, $base.'/');

        if (! $insideBase && ! $insideCanonical) {
            throw UnsafePathException::because($normalized, 'it is outside the application ('.$this->basePath().').');
        }

        $root = $insideBase ? $this->basePath() : $base;
        $current = $root;

        foreach (explode('/', ltrim(substr($normalized, strlen($root)), '/')) as $segment) {
            if ($segment === '') {
                continue;
            }

            $current .= '/'.$segment;

            if (is_link($current)) {
                throw UnsafePathException::because($this->relative($normalized), sprintf('%s is a symbolic link.', $this->relative($current)));
            }

            if (! file_exists($current)) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * $path relative to the application ("app/GCEGo/Pages/dashboard.php").
     */
    public function relative(string $path): string
    {
        foreach ([$this->basePath(), $this->canonicalBase()] as $base) {
            if (str_starts_with($path, $base.'/')) {
                return substr($path, strlen($base) + 1);
            }
        }

        return $path;
    }

    private function canonicalBase(): string
    {
        $real = realpath($this->basePath());

        return $real === false ? $this->basePath() : rtrim($real, '/');
    }
}
