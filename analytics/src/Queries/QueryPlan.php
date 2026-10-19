<?php

declare(strict_types=1);

namespace Gcego\Analytics\Queries;

use Gcego\Analytics\Datasets\Dataset;
use Gcego\Analytics\Dimensions\Dimension;
use Gcego\Analytics\Filters\AnalyticsFilter;
use Gcego\Analytics\Filters\DateRange;
use Gcego\Analytics\Measures\Measure;
use Gcego\Analytics\TimeSeries\TimeInterval;

/**
 * A validated query: every column is a declared, authorized field or
 * dimension of the dataset; operators, period and sizes are within limits.
 * Executors build SQL only from plans.
 */
final readonly class QueryPlan
{
    /**
     * @param  list<Measure>  $measures
     * @param  list<Dimension>  $dimensions
     * @param  list<AnalyticsFilter>  $filters  Each target resolved to its column in $columns.
     * @param  array<string, string>  $columns  Field or dimension name => table column.
     * @param  array<string, 'asc'|'desc'>  $sort
     */
    public function __construct(
        public Dataset $dataset,
        public array $measures,
        public array $dimensions,
        public array $filters,
        public array $columns,
        public ?DateRange $range,
        public ?string $dateColumn,
        public ?TimeInterval $interval,
        public array $sort,
        public int $limit,
        public int $offset,
        public bool $withTotal,
        public int $minimumGroupSize,
        public string $timezone,
    ) {}

    public function isGrouped(): bool
    {
        return $this->dimensions !== [] || $this->interval !== null;
    }

    public function column(string $name): string
    {
        return $this->columns[$name] ?? $name;
    }
}
