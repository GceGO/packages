<?php

declare(strict_types=1);

namespace Gcego\Analytics\Reports;

use Gcego\Analytics\Filters\DateRange;
use Gcego\Analytics\Filters\DateRangePreset;
use Gcego\Analytics\Security\AnalyticsContext;

/**
 * A registered report, ready to run for a context:
 *
 *     $analytics->report('student-performance')->run(filters: ['level' => 'o'], page: 2);
 */
final readonly class ReportHandle
{
    public function __construct(private Report $report, private ReportManager $reports, private AnalyticsContext $context) {}

    /**
     * @param  array<array-key, mixed>  $filters  Values of the report's declared filters.
     */
    public function run(array $filters = [], DateRange|DateRangePreset|string|null $range = null, ?string $sort = null, ?string $direction = null, int $page = 1, ?int $perPage = null): ReportResult
    {
        $range = is_string($range) || $range instanceof DateRangePreset ? DateRange::preset($range, $this->context->timezone) : $range;

        return $this->reports->run($this->report, new ReportRequest($filters, $range, $sort, $direction, $page, $perPage), $this->context);
    }

    public function definition(): Report
    {
        return $this->report;
    }
}
