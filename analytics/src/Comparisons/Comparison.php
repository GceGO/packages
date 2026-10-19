<?php

declare(strict_types=1);

namespace Gcego\Analytics\Comparisons;

use Gcego\Analytics\Exceptions\AnalyticsQueryException;
use Gcego\Analytics\Filters\DateRange;

/**
 * Which period a value is compared with.
 */
final readonly class Comparison
{
    public function __construct(public ComparisonMode $mode, public ?DateRange $custom = null)
    {
        if ($mode === ComparisonMode::Custom && $custom === null) {
            throw AnalyticsQueryException::rejected('comparison', 'A custom comparison needs a period.');
        }
    }

    public static function previousPeriod(): self
    {
        return new self(ComparisonMode::PreviousPeriod);
    }

    public static function previousYear(): self
    {
        return new self(ComparisonMode::PreviousYear);
    }

    public static function with(DateRange $range): self
    {
        return new self(ComparisonMode::Custom, $range);
    }

    public function rangeFor(DateRange $range): ?DateRange
    {
        return match ($this->mode) {
            ComparisonMode::None => null,
            ComparisonMode::PreviousPeriod => $range->previousPeriod(),
            ComparisonMode::PreviousYear => $range->previousYear(),
            ComparisonMode::Custom => $this->custom,
        };
    }

    public function key(): string
    {
        return $this->mode->value.($this->custom === null ? '' : ':'.$this->custom->key());
    }
}
