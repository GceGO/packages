<?php

declare(strict_types=1);

namespace Gcego\Navigation\Resolution;

use Gcego\Navigation\Components\Item;

/**
 * Scores how well an item matches the current page (0: not at all). The
 * navigation marks only the best-scoring item active, so a general item
 * ("/admin") never competes with a specific one ("/admin/students").
 *
 * - Its own route: active on that route name (and, with route parameters,
 *   only on that exact URL).
 * - Its own path or URL: active on exactly that path (same host).
 * - Active patterns, segment-aware (never substrings):
 *     "admin.subjects.*"     admin.subjects, admin.subjects.index, admin.subjects.show …
 *     "admin.*.index"        one segment for each inner "*"
 *     "/admin/subjects/*"    /admin/subjects and every path below it
 *     "/admin/subjects/{id}" exactly one segment in its place
 *   so "admin.subject.*" never matches admin.subjects.index.
 * - Exact matches beat patterns; longer patterns beat shorter ones.
 * - With query matching (Item::MatchQuery or the config), the item's query
 *   parameters must also be in the current URL.
 */
final readonly class ActiveStateResolver
{
    public function __construct(private bool $matchQueryString) {}

    public function score(Item $item, ?string $url, NavigationContext $context): int
    {
        if ($url === null) {
            return 0;
        }

        if (($item->matchesQuery() ?? $this->matchQueryString) && ! self::queryMatches($url, $context->query)) {
            return 0;
        }

        $scores = [0];
        $route = $item->routeName();
        $path = self::pathOf($url, $context->host);

        if ($route !== null && $context->route === $route && ($item->parameters() === [] || $path === $context->path)) {
            $scores[] = 10000 + strlen($route);
        } elseif ($route === null && $path !== null && $path === $context->path) {
            $scores[] = 10000 + strlen($path);
        }

        foreach ($item->activePatterns() as $pattern) {
            $matches = str_starts_with($pattern, '/') ? self::pathMatches($pattern, $context->path) : ($context->route !== null && self::routeMatches($pattern, $context->route));

            if ($matches) {
                $scores[] = 1000 + strlen(str_replace(['*', '.', '/'], '', $pattern));
            }
        }

        return max($scores);
    }

    public static function routeMatches(string $pattern, string $route): bool
    {
        return preg_match(self::regex($pattern, '.'), $route) === 1;
    }

    public static function pathMatches(string $pattern, string $path): bool
    {
        return preg_match(self::regex(self::normalize($pattern), '/'), self::normalize($path)) === 1;
    }

    /**
     * A normalized path ("/admin/students"), or null for another host or a non-web URL.
     */
    public static function pathOf(string $url, string $host): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || (isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true))) {
            return null;
        }

        if (isset($parts['host']) && strtolower($parts['host']) !== strtolower($host)) {
            return null;
        }

        return self::normalize($parts['path'] ?? '/');
    }

    public static function normalize(string $path): string
    {
        $path = '/'.trim(rawurldecode($path), '/');

        return $path === '/' ? '/' : $path;
    }

    /**
     * @param  array<string, mixed>  $current
     */
    private static function queryMatches(string $url, array $current): bool
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $expected);

        foreach ($expected as $key => $value) {
            if (! array_key_exists($key, $current) || $current[$key] != $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  non-empty-string  $separator
     */
    private static function regex(string $pattern, string $separator): string
    {
        $segments = explode($separator, $pattern);
        $quoted = preg_quote($separator, '#');
        $regex = '';

        foreach ($segments as $index => $segment) {
            $last = $index === array_key_last($segments);
            $prefix = $index === 0 ? '' : $quoted;

            if ($segment === '*' && $last && $index > 0) {
                $regex .= '(?:'.$quoted.'.+)?';
            } elseif ($segment === '*' || preg_match('/^\{[A-Za-z_][A-Za-z0-9_]*\??\}$/', $segment) === 1) {
                $regex .= $prefix.'[^'.$quoted.']+';
            } else {
                $regex .= $prefix.preg_quote($segment, '#');
            }
        }

        return '#^'.$regex.'$#';
    }
}
