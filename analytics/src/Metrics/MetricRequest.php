<?php

declare(strict_types=1);

namespace Gcego\Analytics\Metrics;

use Gcego\Analytics\Comparisons\Comparison;
use Gcego\Analytics\Filters\AnalyticsFilter;
use Gcego\Analytics\Filters\DateRange;
use Gcego\Analytics\TimeSeries\TimeInterval;

/**
 * How to calculate a metric: period, comparison, extra filters (already
 * validated), a time series interval or a dimension to group by.
 * $strict refuses filters the metric's dataset does not have (direct API
 * calls); dashboards apply shared filters only where they fit.
 */
final readonly class MetricRequest
{
    /**
     * @param  list<AnalyticsFilter>  $filters
     */
    public function __construct(
        public ?DateRange $range = null,
        public ?Comparison $comparison = null,
        public array $filters = [],
        public ?TimeInterval $interval = null,
        public ?string $groupBy = null,
        public int $limit = 12,
        public bool $strict = true,
        public bool $compareSeries = false,
    ) {}

    public function withRange(?DateRange $range): self
    {
        return new self($range, null, $this->filters, $this->interval, $this->groupBy, $this->limit, $this->strict, false);
    }

    public function key(): string
    {
        return implode('|', [
            $this->range?->key() ?? '-', $this->comparison?->key() ?? '-',
            implode(',', array_map(static fn (AnalyticsFilter $filter): string => $filter->key(), $this->filters)),
            $this->interval->value ?? '-', $this->groupBy ?? '-', (string) $this->limit, $this->strict ? 's' : 'l', $this->compareSeries ? 'c' : '-',
        ]);
    }
}
