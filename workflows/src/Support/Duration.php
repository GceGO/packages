<?php

declare(strict_types=1);

namespace Gcego\Workflows\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Trusted durations of definitions: "30 minutes", "48 hours", "3 days", "2 weeks".
 */
final class Duration
{
    public const string Pattern = '/^([1-9]\d{0,4})\s*(minute|minutes|hour|hours|day|days|week|weeks)$/';

    public static function valid(mixed $value): bool
    {
        return is_string($value) && preg_match(self::Pattern, trim($value)) === 1;
    }

    public static function after(string $duration, ?DateTimeInterface $from = null): CarbonImmutable
    {
        preg_match(self::Pattern, trim($duration), $match);
        $from = $from === null ? CarbonImmutable::now() : CarbonImmutable::instance($from);
        $amount = (int) ($match[1] ?? 0);

        return match (rtrim($match[2] ?? 'minute', 's')) {
            'hour' => $from->addHours($amount),
            'day' => $from->addDays($amount),
            'week' => $from->addWeeks($amount),
            default => $from->addMinutes($amount),
        };
    }
}
