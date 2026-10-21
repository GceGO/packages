<?php

declare(strict_types=1);

namespace Gcego\Components\Support;

use Closure;

/**
 * Validates responsive values: one value, or one value per breakpoint.
 *
 *     2
 *     ['default' => 1, 'md' => 2, 'xl' => 4]
 */
final class Responsive
{
    /**
     * @var list<string>
     */
    public const array Breakpoints = ['default', 'sm', 'md', 'lg', 'xl', '2xl'];

    /**
     * @param  Closure(mixed): bool  $accepts
     */
    public static function accepts(mixed $value, Closure $accepts): bool
    {
        if (! is_array($value)) {
            return $accepts($value);
        }

        if ($value === []) {
            return false;
        }

        foreach ($value as $breakpoint => $item) {
            if (! in_array($breakpoint, self::Breakpoints, true) || ! $accepts($item)) {
                return false;
            }
        }

        return true;
    }

    public static function describe(string $item): string
    {
        return sprintf('%s, or an array of them keyed by %s', $item, implode(', ', self::Breakpoints));
    }
}
