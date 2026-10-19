<?php

declare(strict_types=1);

namespace Gcego\Analytics\Measures;

/**
 * Database aggregates. Ratio, percentage, rate and derived metrics are
 * computed from other metrics (MetricKind), never from expressions.
 */
enum Aggregate: string
{
    case Count = 'count';
    case Sum = 'sum';
    case Average = 'avg';
    case Minimum = 'min';
    case Maximum = 'max';
    case CountDistinct = 'count_distinct';

    public function needsField(): bool
    {
        return $this !== self::Count;
    }

    /**
     * Whether an empty set yields zero (counts, sums) rather than "no value" (averages, extremes).
     */
    public function zeroWhenEmpty(): bool
    {
        return in_array($this, [self::Count, self::CountDistinct, self::Sum], true);
    }

    public function sql(string $wrappedColumn): string
    {
        return match ($this) {
            self::Count => $wrappedColumn === '*' ? 'COUNT(*)' : 'COUNT('.$wrappedColumn.')',
            self::CountDistinct => 'COUNT(DISTINCT '.$wrappedColumn.')',
            self::Sum => 'SUM('.$wrappedColumn.')',
            self::Average => 'AVG('.$wrappedColumn.')',
            self::Minimum => 'MIN('.$wrappedColumn.')',
            self::Maximum => 'MAX('.$wrappedColumn.')',
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
