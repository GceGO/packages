<?php

declare(strict_types=1);

namespace Gcego\Analytics\Reports;

use Carbon\CarbonImmutable;
use Gcego\Analytics\AnalyticsConfig;
use Gcego\Analytics\Cache\AnalyticsCache;
use Gcego\Analytics\Datasets\Dataset;
use Gcego\Analytics\Definitions\AnalyticsDefinitionValidator;
use Gcego\Analytics\Dimensions\DimensionLabelResolver;
use Gcego\Analytics\Events\ReportExecuted;
use Gcego\Analytics\Exceptions\AnalyticsAuthorizationException;
use Gcego\Analytics\Exceptions\AnalyticsQueryException;
use Gcego\Analytics\Exceptions\InvalidDefinitionException;
use Gcego\Analytics\Filters\DateRange;
use Gcego\Analytics\Filters\FilterResolver;
use Gcego\Analytics\Measures\Measure;
use Gcego\Analytics\Queries\AnalyticsQuery;
use Gcego\Analytics\Queries\AnalyticsQueryPlanner;
use Gcego\Analytics\Queries\EloquentAnalyticsExecutor;
use Gcego\Analytics\Registry\AnalyticsDefinitions;
use Gcego\Analytics\Security\AnalyticsAuthorizer;
use Gcego\Analytics\Security\AnalyticsContext;
use Gcego\Analytics\Support\DefinitionHash;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Runs reports: resolve, check references, authorize (report and dataset),
 * validate filters, period, sort and page, then one grouped query (plus a
 * count of groups) through the planner and executor. Dimension keys get
 * labels as the viewer; columns the viewer may not see are left out.
 */
final readonly class ReportManager
{
    public function __construct(
        private AnalyticsDefinitions $definitions,
        private AnalyticsDefinitionValidator $validator,
        private AnalyticsAuthorizer $authorizer,
        private AnalyticsQueryPlanner $planner,
        private EloquentAnalyticsExecutor $executor,
        private DimensionLabelResolver $labels,
        private AnalyticsCache $cache,
        private AnalyticsConfig $config,
        private Dispatcher $events,
    ) {}

    public function run(Report $report, ReportRequest $request, AnalyticsContext $context): ReportResult
    {
        $dataset = $this->authorize($report, $context);
        $started = hrtime(true);
        $perPage = $request->export ? min($request->perPage ?? $this->config->exportChunk, $this->config->exportChunk) : min($request->perPage ?? $report->perPage(), $this->config->maxPageSize);
        $page = max(1, $request->page);
        $range = $request->range ?? ($request->defaultRange && $report->defaultRange() !== null ? DateRange::preset($report->defaultRange(), $context->timezone) : null);
        $filters = FilterResolver::resolve($report->filters(), $request->filters);
        $sortName = $request->sort ?? $report->sort();
        $direction = in_array($request->direction, ['asc', 'desc'], true) ? (string) $request->direction : $report->direction();
        $sortColumn = $sortName === null ? null : $report->column($sortName);

        if ($sortName !== null && ($sortColumn === null || ! $sortColumn->isSortable())) {
            throw AnalyticsQueryException::rejected('sort', 'That column cannot be sorted.');
        }

        $visible = $this->visibleColumns($report, $dataset, $context);
        $dimensions = [];
        $measures = [];

        foreach ($visible as $column) {
            if ($column->isMeasure()) {
                $measures[] = new Measure($column->nodeName(), $column->aggregate() ?? throw new \LogicException('No aggregate.'), $column->field());
            } else {
                $dimensions[] = $column->dimension();
            }
        }

        if ($measures === []) {
            throw AnalyticsAuthorizationException::for('see any column of this report');
        }

        $sort = [];

        if ($sortColumn !== null && in_array($sortColumn, $visible, true)) {
            $sort[$sortColumn->isMeasure() ? $sortColumn->nodeName() : $sortColumn->dimension()] = $direction === 'asc' ? 'asc' : 'desc';
        }

        $query = new AnalyticsQuery(
            dataset: $dataset,
            measures: $measures,
            dimensions: array_values(array_unique($dimensions)),
            filters: $filters,
            range: $range,
            dateColumn: $report->dateColumn() ?? $dataset->dateColumn(),
            sort: $sort,
            limit: $perPage,
            offset: ($page - 1) * $perPage,
            withTotal: true,
            rowCeiling: $request->export ? $this->config->exportMaxRows : null,
        );

        [$raw, $cached] = $this->cache->remember('report', $report->nodeName(), DefinitionHash::of($report).':'.DefinitionHash::of($dataset), [$dataset->nodeName()], $query->key(), $context, $request->export ? 0 : null, function () use ($query, $context, $visible, $dataset): array {
            $rows = $this->executor->execute($this->planner->plan($query, $context), $context);
            $labels = [];

            foreach ($visible as $column) {
                if (! $column->isMeasure() && ($dimension = $dataset->dimension($column->dimension())) !== null) {
                    $labels[$column->nodeName()] = $this->labels->labels($dimension, array_map(static fn (array $row): ?string => $row['dimensions'][$dimension->nodeName()] ?? null, $rows->rows), $context);
                }
            }

            return ['rows' => $rows->rows, 'labels' => $labels, 'total' => $rows->total ?? count($rows->rows), 'at' => CarbonImmutable::now()->toIso8601String()];
        });

        $result = $this->result($report, $visible, $raw, $cached, $page, $perPage, $range, $request->filters, $sortColumn?->nodeName(), $direction, $context);
        $this->events->dispatch(new ReportExecuted($report->nodeName(), $context->tenant, $result->total, $cached, round((hrtime(true) - $started) / 1e6, 3), $context->correlation));

        return $result;
    }

    /**
     * Checks references and access; returns the report's dataset.
     */
    public function authorize(Report $report, AnalyticsContext $context): Dataset
    {
        $problems = $this->validator->report($report);

        if ($problems !== []) {
            throw InvalidDefinitionException::invalid('Report "'.$report->nodeName().'"', implode(' ', $problems));
        }

        $dataset = $this->definitions->dataset($report->datasetName());

        if (! $this->authorizer->report($report, $dataset, $context)) {
            throw AnalyticsAuthorizationException::for('see this report');
        }

        return $dataset;
    }

    public function allows(Report $report, AnalyticsContext $context): bool
    {
        try {
            $this->authorize($report, $context);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Columns the viewer may see (columns on sensitive fields or restricted dimensions are left out).
     *
     * @return list<ReportColumn>
     */
    public function visibleColumns(Report $report, Dataset $dataset, AnalyticsContext $context): array
    {
        return array_values(array_filter($report->columns(), function (ReportColumn $column) use ($dataset, $context): bool {
            if ($column->isMeasure()) {
                $field = $column->field() === null ? null : $dataset->field((string) $column->field());

                return $column->field() === null || ($field !== null && $this->authorizer->field($field, $context));
            }

            $dimension = $dataset->dimension($column->dimension());

            return $dimension !== null && $this->authorizer->dimension($dimension, $context);
        }));
    }

    /**
     * @param  list<ReportColumn>  $visible
     * @param  array<string, mixed>  $raw
     * @param  array<array-key, mixed>  $filters
     */
    private function result(Report $report, array $visible, array $raw, bool $cached, int $page, int $perPage, ?DateRange $range, array $filters, ?string $sort, string $direction, AnalyticsContext $context): ReportResult
    {
        $labels = is_array($raw['labels'] ?? null) ? $raw['labels'] : [];
        $rows = [];

        foreach (is_array($raw['rows'] ?? null) ? $raw['rows'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $cells = [];
            $suppressed = ($row['suppressed'] ?? false) === true;

            foreach ($visible as $column) {
                if ($column->isMeasure()) {
                    $value = is_array($row['values'] ?? null) ? ($row['values'][$column->nodeName()] ?? null) : null;
                    $value = is_int($value) || is_float($value) ? $value : null;
                    $cells[$column->nodeName()] = ['value' => $value, 'formatted' => $suppressed ? (string) __('gcego-analytics::messages.suppressed') : $column->format()->format($value, $column->precision(), $column->currency(), $column->unit(), $context->timezone), 'key' => null];

                    continue;
                }

                $key = is_array($row['dimensions'] ?? null) ? ($row['dimensions'][$column->dimension()] ?? null) : null;
                $key = is_string($key) ? $key : null;
                $columnLabels = is_array($labels[$column->nodeName()] ?? null) ? $labels[$column->nodeName()] : [];
                $label = $key === null ? DimensionLabelResolver::notSet() : (is_string($columnLabels[$key] ?? null) ? $columnLabels[$key] : $key);
                $cells[$column->nodeName()] = ['value' => $label, 'formatted' => $column->format()->format($label, timezone: $context->timezone), 'key' => $key];
            }

            $rows[] = ['cells' => $cells, 'suppressed' => $suppressed];
        }

        return new ReportResult(
            report: $report->nodeName(),
            title: $report->title(),
            columns: array_map(static fn (ReportColumn $column): array => ['name' => $column->nodeName(), 'label' => $column->label(), 'kind' => $column->isMeasure() ? 'measure' : 'dimension', 'format' => $column->format()->value, 'sortable' => $column->isSortable(), 'drill_down' => $column->drillDown()], $visible),
            rows: $rows,
            page: $page,
            perPage: $perPage,
            total: is_int($raw['total'] ?? null) ? $raw['total'] : count($rows),
            range: $range,
            filters: $filters,
            sort: $sort,
            direction: $direction,
            generatedAt: is_string($raw['at'] ?? null) ? CarbonImmutable::parse($raw['at']) : CarbonImmutable::now(),
            cached: $cached,
        );
    }
}
