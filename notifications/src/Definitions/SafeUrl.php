<?php

declare(strict_types=1);

namespace Gcego\Notifications\Definitions;

/**
 * Notification links: root-relative paths, https URLs, or URLs on the
 * application's own origin (app.url). Never javascript:, data:,
 * protocol-relative or quote-carrying values.
 */
final class SafeUrl
{
    public static function check(mixed $url): bool
    {
        if (! is_string($url) || $url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7F"\'<>\\\\`]/', $url) === 1) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($scheme) || ! is_string($host) || $host === '') {
            return false;
        }

        if (strtolower($scheme) === 'https') {
            return true;
        }

        $app = config('app.url');

        return strtolower($scheme) === 'http' && is_string($app) && parse_url($app, PHP_URL_HOST) === $host;
    }
}
