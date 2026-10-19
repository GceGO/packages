<?php

declare(strict_types=1);

namespace Gcego\Analytics\Integration\Actions;

use Gcego\Actions\Contracts\ActionHandler;
use Gcego\Actions\Execution\ActionContext;
use Gcego\Analytics\AnalyticsManager;
use Gcego\Analytics\Exceptions\AnalyticsQueryException;
use Gcego\Analytics\Exports\ExportManager;
use Gcego\Analytics\Models\AnalyticsExport;
use Gcego\Analytics\Registry\AnalyticsDefinitions;
use Gcego\Analytics\Reports\ReportRequest;
use Illuminate\Validation\ValidationException;

/**
 * A gcego/actions handler exporting a report as the acting user (the
 * action's authorization runs first; the export's own runs again):
 *
 *     final class ExportSubjectPerformance extends ExportReport
 *     {
 *         protected function report(): string { return 'subject-performance'; }
 *     }
 *
 * Validated action input values named like the report's filters are used as filters.
 */
abstract class ExportReport implements ActionHandler
{
    abstract protected function report(): string;

    protected function format(): string
    {
        return 'csv';
    }

    public function handle(ActionContext $context): AnalyticsExport
    {
        $report = app(AnalyticsDefinitions::class)->report($this->report());
        $values = array_intersect_key($context->input->values, array_flip(array_map(static fn ($filter): string => $filter->nodeName(), $report->filters())));

        try {
            return app(ExportManager::class)->request($report, new ReportRequest($values), $this->format(), app(AnalyticsManager::class)->context());
        } catch (AnalyticsQueryException $exception) {
            throw ValidationException::withMessages(['report' => $exception->getMessage()]);
        }
    }
}
