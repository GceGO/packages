<?php

declare(strict_types=1);

namespace Gcego\Tables\Support;

/**
 * URL checks for values that come from records.
 */
final class SafeUrl
{
    /**
     * An http(s) URL or a root-relative path ("/storage/a.png"), for images.
     */
    public static function web(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7F"\'<>\\\\]/', $url) === 1) {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true) && is_string($host) && $host !== '' ? $url : null;
    }

    /**
     * A link target: http(s), mailto, or a root-relative path. Never javascript:, data: or vbscript:.
     */
    public static function link(string $url): ?string
    {
        $url = trim($url);

        if (preg_match('/^mailto:[^\s<>"\'\\\\]{3,320}$/i', $url) === 1) {
            return $url;
        }

        return self::web($url);
    }

    /**
     * A relative storage path without traversal, schemes or control characters.
     */
    public static function isStoragePath(string $path): bool
    {
        return strlen($path) <= 1024
            && preg_match('/^[A-Za-z0-9_\-][A-Za-z0-9_\-.\/ ]*$/', $path) === 1
            && ! str_contains($path, '..')
            && ! str_contains($path, '//');
    }
}
