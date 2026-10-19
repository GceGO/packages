<?php

declare(strict_types=1);

namespace Gcego\Analytics\Reports;

use Gcego\Analytics\Filters\DateRange;

/**
 * Untrusted report parameters (validated by ReportManager): filter values
 * by declared filter, period, sort column and direction, page.
 */
final readonly class ReportRequest
{
    /**
     * @param  array<array-key, mixed>  $filters
     */
    public function __construct(
        public array $filters = [],
        public ?DateRange $range = null,
        public ?string $sort = null,
        public ?string $direction = null,
        public int $page = 1,
        public ?int $perPage = null,
        public bool $defaultRange = true,
        /** An export read: larger pages, bounded by exports.max_rows, never cached. */
        public bool $export = false,
    ) {}
}
