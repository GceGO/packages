<?php

declare(strict_types=1);

namespace Gcego\Analytics\Metrics;

use Gcego\Analytics\Measures\Aggregate;

/**
 * How a metric is calculated: a database aggregate, or from other metrics
 * (ratio = n / d, percentage = n / d × 100, rate = n / d × per, derived = a formula).
 */
enum MetricKind: string
{
    case Count = 'count';
    case Sum = 'sum';
    case Average = 'avg';
    case Minimum = 'min';
    case Maximum = 'max';
    case CountDistinct = 'count_distinct';
    case Ratio = 'ratio';
    case Percentage = 'percentage';
    case Rate = 'rate';
    case Derived = 'derived';

    public function aggregate(): ?Aggregate
    {
        return Aggregate::tryFrom($this->value);
    }

    public function isComposite(): bool
    {
        return $this->aggregate() === null;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
