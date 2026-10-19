<?php

declare(strict_types=1);

namespace Gcego\Panels\Support;

/**
 * URL checks for trusted branding values: root-relative paths or https URLs only.
 */
final class SafeUrl
{
    public static function asset(mixed $url): bool
    {
        if (! is_string($url) || $url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7F"\'<>\\\\`]/', $url) === 1) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($scheme) && strtolower($scheme) === 'https' && is_string($host) && $host !== '';
    }
}
