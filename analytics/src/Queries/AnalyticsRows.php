<?php

declare(strict_types=1);

namespace Gcego\Analytics\Queries;

/**
 * Aggregated rows. Each row has its dimension keys (null: not set), its
 * time bucket, its measure values, how many records it aggregates ($count)
 * and whether its values were suppressed (a group smaller than the
 * dataset's minimum group size: values are null, the count is hidden).
 */
final readonly class AnalyticsRows
{
    /**
     * @param  list<array{dimensions: array<string, string|null>, bucket: string|null, values: array<string, int|float|null>, count: int|null, suppressed: bool}>  $rows
     */
    public function __construct(
        public array $rows,
        public bool $truncated = false,
        public ?int $total = null,
        public float $durationMs = 0.0,
    ) {}

    /**
     * The values of an ungrouped query.
     *
     * @return array<string, int|float|null>
     */
    public function first(): array
    {
        return $this->rows[0]['values'] ?? [];
    }

    public function suppressed(): bool
    {
        return ($this->rows[0]['suppressed'] ?? false) === true;
    }
}
