<?php

declare(strict_types=1);

namespace Gcego\Analytics\TimeSeries;

use Carbon\CarbonImmutable;

/**
 * Time-series buckets. Keys are stable strings in the report's time zone:
 * "2026-03-01 14:00", "2026-03-01", "2026-03-02" (the Monday of an ISO week),
 * "2026-03", "2026-Q1", "2026".
 */
enum TimeInterval: string
{
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';

    /**
     * A readable label of a bucket key ("12 Mar", "Week of 9 Mar", "Mar 2026", "Q1 2026").
     */
    public function label(string $key, string $timezone): string
    {
        try {
            return match ($this) {
                self::Hour => CarbonImmutable::createFromFormat('Y-m-d H:i', $key, $timezone)?->isoFormat('D MMM HH:mm') ?? $key,
                self::Day => CarbonImmutable::createFromFormat('!Y-m-d', $key, $timezone)?->isoFormat('D MMM YYYY') ?? $key,
                self::Week => (string) __('gcego-analytics::messages.week_of', ['date' => CarbonImmutable::createFromFormat('!Y-m-d', $key, $timezone)?->isoFormat('D MMM YYYY') ?? $key]),
                self::Month => CarbonImmutable::createFromFormat('!Y-m', $key, $timezone)?->isoFormat('MMM YYYY') ?? $key,
                self::Quarter => preg_match('/^(\d{4})-Q([1-4])$/', $key, $match) === 1 ? 'Q'.$match[2].' '.$match[1] : $key,
                self::Year => $key,
            };
        } catch (\Throwable) {
            return $key;
        }
    }

    /**
     * The bucket key of a moment (already in the target time zone).
     */
    public function key(CarbonImmutable $moment): string
    {
        return match ($this) {
            self::Hour => $moment->format('Y-m-d H:00'),
            self::Day => $moment->format('Y-m-d'),
            self::Week => $moment->startOfWeek(CarbonImmutable::MONDAY)->format('Y-m-d'),
            self::Month => $moment->format('Y-m'),
            self::Quarter => $moment->format('Y').'-Q'.$moment->quarter,
            self::Year => $moment->format('Y'),
        };
    }

    public function start(CarbonImmutable $moment): CarbonImmutable
    {
        return match ($this) {
            self::Hour => $moment->startOfHour(),
            self::Day => $moment->startOfDay(),
            self::Week => $moment->startOfWeek(CarbonImmutable::MONDAY),
            self::Month => $moment->startOfMonth(),
            self::Quarter => $moment->startOfQuarter(),
            self::Year => $moment->startOfYear(),
        };
    }

    public function next(CarbonImmutable $moment): CarbonImmutable
    {
        return match ($this) {
            self::Hour => $moment->addHour(),
            self::Day => $moment->addDay(),
            self::Week => $moment->addWeek(),
            self::Month => $moment->addMonthNoOverflow(),
            self::Quarter => $moment->addQuarterNoOverflow(),
            self::Year => $moment->addYear(),
        };
    }

    /**
     * Every bucket key from $from to $to inclusive (both in the target time zone), at most $max.
     *
     * @return list<string>
     */
    public function keys(CarbonImmutable $from, CarbonImmutable $to, int $max): array
    {
        $keys = [];

        for ($cursor = $this->start($from); $cursor <= $to && count($keys) < $max; $cursor = $this->next($cursor)) {
            $keys[] = $this->key($cursor);
        }

        return $keys;
    }

    /**
     * How many buckets a range spans (for limits, before querying).
     */
    public function count(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $start = $this->start($from);

        return (int) match ($this) {
            self::Hour => floor($start->diffInHours($to)) + 1,
            self::Day => floor($start->diffInDays($to)) + 1,
            self::Week => floor($start->diffInWeeks($to)) + 1,
            self::Month => floor($start->diffInMonths($to)) + 1,
            self::Quarter => floor($start->diffInMonths($to) / 3) + 1,
            self::Year => floor($start->diffInYears($to)) + 1,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
