<?php

declare(strict_types=1);

namespace Gcego\Analytics\Filters;

use Carbon\CarbonImmutable;
use Gcego\Analytics\Exceptions\AnalyticsQueryException;
use Throwable;

/**
 * A reporting period in a time zone: from the first moment of $from's day
 * (inclusive) to the end of $to's day (inclusive). Queries bind it as
 * [start, endExclusive) converted to the database time zone, so the
 * boundaries are exact whatever the time zone or daylight saving.
 */
final readonly class DateRange
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $endExclusive,
        public string $timezone,
        public ?DateRangePreset $preset = null,
    ) {
        if ($endExclusive <= $start) {
            throw AnalyticsQueryException::rejected('date_range', 'The end of the period must be after its start.');
        }
    }

    public static function preset(DateRangePreset|string $preset, string $timezone, ?CarbonImmutable $now = null): self
    {
        $preset = $preset instanceof DateRangePreset ? $preset : (DateRangePreset::tryFrom($preset) ?? throw AnalyticsQueryException::rejected('date_range', 'Unknown period.'));
        $now = ($now ?? CarbonImmutable::now())->setTimezone($timezone);
        $today = $now->startOfDay();

        [$start, $end] = match ($preset) {
            DateRangePreset::Today => [$today, $today->addDay()],
            DateRangePreset::Yesterday => [$today->subDay(), $today],
            DateRangePreset::Last7Days => [$today->subDays(6), $today->addDay()],
            DateRangePreset::Last30Days => [$today->subDays(29), $today->addDay()],
            DateRangePreset::Last90Days => [$today->subDays(89), $today->addDay()],
            DateRangePreset::CurrentMonth => [$today->startOfMonth(), $today->startOfMonth()->addMonthNoOverflow()],
            DateRangePreset::PreviousMonth => [$today->startOfMonth()->subMonthNoOverflow(), $today->startOfMonth()],
            DateRangePreset::CurrentYear => [$today->startOfYear(), $today->startOfYear()->addYear()],
            DateRangePreset::PreviousYear => [$today->startOfYear()->subYear(), $today->startOfYear()],
            DateRangePreset::Custom => throw AnalyticsQueryException::rejected('date_range', 'A custom period needs dates.'),
        };

        return new self($start, $end, $timezone, $preset);
    }

    /**
     * Two calendar dates ("2026-01-01", "2026-01-31"), inclusive, in a time zone.
     */
    public static function between(string $from, string $to, string $timezone): self
    {
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $from, $timezone);
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $to, $timezone);
        } catch (Throwable) {
            $start = $end = false;
        }

        if (! $start instanceof CarbonImmutable || ! $end instanceof CarbonImmutable || $start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to) {
            throw AnalyticsQueryException::rejected('date_range', 'Use dates such as 2026-01-31.');
        }

        if ($end < $start) {
            throw AnalyticsQueryException::rejected('date_range', 'The end date must not be before the start date.');
        }

        return new self($start, $end->addDay(), $timezone, DateRangePreset::Custom);
    }

    /**
     * The last moment of the period (inclusive), for display.
     */
    public function end(): CarbonImmutable
    {
        return $this->endExclusive->subSecond();
    }

    public function days(): int
    {
        return (int) ceil($this->start->diffInDays($this->endExclusive));
    }

    /**
     * The same length of time immediately before this period.
     */
    public function previousPeriod(): self
    {
        if ($this->isWholeMonths()) {
            $months = (int) round($this->start->diffInMonths($this->endExclusive));

            return new self($this->start->subMonthsNoOverflow($months), $this->start, $this->timezone);
        }

        $seconds = (int) $this->start->diffInSeconds($this->endExclusive);

        return new self($this->start->subSeconds($seconds), $this->start, $this->timezone);
    }

    public function previousYear(): self
    {
        return new self($this->start->subYearNoOverflow(), $this->endExclusive->subYearNoOverflow(), $this->timezone);
    }

    /**
     * Bound values for SQL, in the database (application) time zone.
     *
     * @return array{0: string, 1: string}
     */
    public function bindings(string $databaseTimezone): array
    {
        return [$this->start->setTimezone($databaseTimezone)->format('Y-m-d H:i:s'), $this->endExclusive->setTimezone($databaseTimezone)->format('Y-m-d H:i:s')];
    }

    /**
     * @return array{from: string, to: string, timezone: string, preset: string|null}
     */
    public function toArray(): array
    {
        return ['from' => $this->start->format('Y-m-d'), 'to' => $this->end()->format('Y-m-d'), 'timezone' => $this->timezone, 'preset' => $this->preset?->value];
    }

    public function key(): string
    {
        return $this->start->toIso8601String().'/'.$this->endExclusive->toIso8601String();
    }

    private function isWholeMonths(): bool
    {
        return $this->start->day === 1 && $this->endExclusive->day === 1 && $this->start->format('H:i:s') === '00:00:00' && $this->endExclusive->format('H:i:s') === '00:00:00';
    }
}
