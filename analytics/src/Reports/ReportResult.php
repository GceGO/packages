<?php

declare(strict_types=1);

namespace Gcego\Analytics\Reports;

use Carbon\CarbonImmutable;
use Gcego\Analytics\Filters\DateRange;

/**
 * A page of a report: columns, rows (each cell raw, formatted and, for
 * dimensions, its key), pagination, the applied period and filters, when
 * it was generated and whether it came from the cache. Only what the
 * viewer may see.
 */
final readonly class ReportResult
{
    /**
     * @param  list<array{name: string, label: string, kind: string, format: string, sortable: bool, drill_down: string|null}>  $columns
     * @param  list<array{cells: array<string, array{value: int|float|string|null, formatted: string, key: string|null}>, suppressed: bool}>  $rows
     * @param  array<array-key, mixed>  $filters
     */
    public function __construct(
        public string $report,
        public string $title,
        public array $columns,
        public array $rows,
        public int $page,
        public int $perPage,
        public int $total,
        public ?DateRange $range,
        public array $filters,
        public ?string $sort,
        public string $direction,
        public CarbonImmutable $generatedAt,
        public bool $cached,
    ) {}

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'report' => $this->report, 'title' => $this->title, 'columns' => $this->columns, 'rows' => $this->rows,
            'page' => $this->page, 'per_page' => $this->perPage, 'total' => $this->total, 'last_page' => $this->lastPage(),
            'range' => $this->range?->toArray(), 'filters' => $this->filters, 'sort' => $this->sort, 'direction' => $this->direction,
            'generated_at' => $this->generatedAt->toIso8601String(), 'cached' => $this->cached,
        ];
    }
}
