<?php

declare(strict_types=1);

namespace Gcego\Analytics\Queries;

use Gcego\Analytics\Datasets\Dataset;
use Gcego\Analytics\Filters\AnalyticsFilter;
use Gcego\Analytics\Filters\DateRange;
use Gcego\Analytics\Measures\Measure;
use Gcego\Analytics\TimeSeries\TimeInterval;

/**
 * What to compute, before validation (see AnalyticsQueryPlanner): built by
 * metric, report and dashboard services from definitions and validated
 * input, or by application code through AnalyticsManager::query().
 */
final readonly class AnalyticsQuery
{
    /**
     * @param  list<Measure>  $measures
     * @param  list<string>  $dimensions
     * @param  list<AnalyticsFilter>  $filters
     * @param  array<string, 'asc'|'desc'>  $sort  Measure alias or dimension name => direction.
     */
    public function __construct(
        public Dataset $dataset,
        public array $measures,
        public array $dimensions = [],
        public array $filters = [],
        public ?DateRange $range = null,
        public ?string $dateColumn = null,
        public ?TimeInterval $interval = null,
        public array $sort = [],
        public ?int $limit = null,
        public int $offset = 0,
        public bool $withTotal = false,
        /** A row bound set by server code (exports); defaults to gcego-analytics.queries.max_rows. */
        public ?int $rowCeiling = null,
    ) {}

    public function key(): string
    {
        return implode('|', [
            implode(',', array_map(static fn (Measure $measure): string => $measure->key(), $this->measures)),
            implode(',', $this->dimensions),
            implode(',', array_map(static fn (AnalyticsFilter $filter): string => $filter->key(), $this->filters)),
            $this->range?->key() ?? '-', $this->dateColumn ?? '-', $this->interval->value ?? '-',
            json_encode($this->sort), (string) $this->limit, (string) $this->offset, $this->withTotal ? 't' : 'f',
        ]);
    }
}
