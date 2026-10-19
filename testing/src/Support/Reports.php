<?php

declare(strict_types=1);

namespace Gcego\Testing\Support;

use BackedEnum;
use UnitEnum;

/**
 * Reads the inspector reports of the GCEGO packages.
 *
 * Every package reports diagnostics with a level ("ok", "warning",
 * "error", as a string or an enum), a check or code, and a message.
 */
final class Reports
{
    /**
     * Diagnostics at the level (and above, for "warning"), as
     * "check: message" lines.
     *
     * @return list<string>
     */
    public static function problems(object $report, string $level = 'error'): array
    {
        $levels = $level === 'warning' ? ['warning', 'error'] : [$level];
        $problems = [];

        foreach (self::diagnostics($report) as $diagnostic) {
            if (in_array(self::level($diagnostic), $levels, true)) {
                $problems[] = self::describe($diagnostic);
            }
        }

        return $problems;
    }

    /**
     * Every check or code reported, at any level.
     *
     * @return list<string>
     */
    public static function checks(object $report): array
    {
        return array_values(array_unique(array_map(static fn (object $diagnostic): string => self::code($diagnostic), self::diagnostics($report))));
    }

    /**
     * @return list<object>
     */
    public static function diagnostics(object $report): array
    {
        $diagnostics = property_exists($report, 'diagnostics') ? $report->diagnostics : [];

        return is_array($diagnostics) ? array_values(array_filter($diagnostics, is_object(...))) : [];
    }

    public static function level(object $diagnostic): string
    {
        $level = property_exists($diagnostic, 'level') ? $diagnostic->level : 'error';

        return strtolower(match (true) {
            $level instanceof BackedEnum => (string) $level->value,
            $level instanceof UnitEnum => $level->name,
            is_string($level) => $level,
            default => 'error',
        });
    }

    public static function code(object $diagnostic): string
    {
        foreach (['check', 'code'] as $property) {
            if (property_exists($diagnostic, $property) && is_string($diagnostic->{$property})) {
                return $diagnostic->{$property};
            }
        }

        return $diagnostic::class;
    }

    public static function describe(object $diagnostic): string
    {
        $message = property_exists($diagnostic, 'message') && is_string($diagnostic->message) ? $diagnostic->message : '';

        return self::code($diagnostic).': '.$message;
    }
}
