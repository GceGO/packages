<?php

declare(strict_types=1);

namespace Gcego\Analytics\Dashboards;

use Gcego\Analytics\Metrics\MetricResult;
use Gcego\Analytics\Reports\ReportResult;

/**
 * A resolved dashboard: the parts the viewer may see, calculated for the
 * state. A part that failed carries an error message instead of data;
 * parts the viewer may not see are absent.
 */
final readonly class DashboardView
{
    /**
     * @param  list<array{name: string, result: MetricResult|null, error: string|null}>  $kpis
     * @param  list<ChartView>  $charts
     * @param  list<array{name: string, result: ReportResult|null, error: string|null}>  $reports
     * @param  list<array{name: string, label: string, multiple: bool, options: array<string, string>}>  $filters
     */
    public function __construct(
        public string $name,
        public string $title,
        public ?string $description,
        public DashboardState $state,
        public array $kpis,
        public array $charts,
        public array $reports,
        public array $filters,
        public ?int $refresh,
    ) {}
}
