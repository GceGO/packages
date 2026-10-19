<?php

declare(strict_types=1);

namespace Gcego\Analytics\Comparisons;

use Gcego\Analytics\Filters\DateRange;

/**
 * A value against its comparison period. $change is null when either side
 * has no value; $percentChange is null when the baseline is zero (a change
 * from nothing has no percentage). Direction: up, down, flat or unknown.
 */
final readonly class ComparisonResult
{
    public function __construct(
        public ComparisonMode $mode,
        public DateRange $range,
        public int|float|null $previous,
        public int|float|null $change,
        public ?float $percentChange,
        public string $direction,
    ) {}

    public static function between(ComparisonMode $mode, DateRange $range, int|float|null $current, int|float|null $previous, int $precision = 2): self
    {
        if ($current === null || $previous === null) {
            return new self($mode, $range, $previous, null, null, 'unknown');
        }

        $change = $current - $previous;
        $change = is_float($change) ? round($change, $precision) : $change;
        $percent = (float) $previous === 0.0 ? null : round(($current - $previous) / abs($previous) * 100, 1);

        return new self($mode, $range, $previous, $change, $percent, $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'));
    }

    /**
     * @return array{mode: string, range: array{from: string, to: string, timezone: string, preset: string|null}, previous: int|float|null, change: int|float|null, percent_change: float|null, direction: string}
     */
    public function toArray(): array
    {
        return ['mode' => $this->mode->value, 'range' => $this->range->toArray(), 'previous' => $this->previous, 'change' => $this->change, 'percent_change' => $this->percentChange, 'direction' => $this->direction];
    }
}
