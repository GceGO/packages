<?php

declare(strict_types=1);

namespace Gcego\Analytics\Dashboards;

use Gcego\Analytics\Dashboard;
use Gcego\Analytics\Datasets\Dataset;
use Gcego\Analytics\Definitions\AnalyticsDefinitionValidator;
use Gcego\Analytics\Dimensions\Dimension;
use Gcego\Analytics\Dimensions\DimensionLabelResolver;
use Gcego\Analytics\Exceptions\AnalyticsAuthorizationException;
use Gcego\Analytics\Exceptions\AnalyticsException;
use Gcego\Analytics\Exceptions\InvalidDefinitionException;
use Gcego\Analytics\Integration\ChartBuilder;
use Gcego\Analytics\Metrics\Metric;
use Gcego\Analytics\Metrics\MetricCalculator;
use Gcego\Analytics\Metrics\MetricRequest;
use Gcego\Analytics\Registry\AnalyticsDefinitions;
use Gcego\Analytics\Reports\ReportManager;
use Gcego\Analytics\Reports\ReportRequest;
use Gcego\Analytics\Security\AnalyticsAuthorizer;
use Gcego\Analytics\Security\AnalyticsContext;
use Throwable;

/**
 * Resolves a dashboard for a state: authorizes the dashboard, then each
 * KPI, chart and report on its own (parts the viewer may not see are left
 * out), and calculates them with the shared period, comparison and
 * filters. A failing part shows an error; the others still render.
 */
final readonly class DashboardResolver
{
    public function __construct(
        private AnalyticsDefinitions $definitions,
        private AnalyticsDefinitionValidator $validator,
        private AnalyticsAuthorizer $authorizer,
        private MetricCalculator $metrics,
        private ReportManager $reports,
        private ChartBuilder $charts,
        private DimensionLabelResolver $labels,
    ) {}

    public function authorize(Dashboard $dashboard, AnalyticsContext $context): void
    {
        $problems = $this->validator->dashboard($dashboard);

        if ($problems !== []) {
            throw InvalidDefinitionException::invalid('Dashboard "'.$dashboard->nodeName().'"', implode(' ', $problems));
        }

        if (! $this->authorizer->dashboard($dashboard, $context)) {
            throw AnalyticsAuthorizationException::for('see this dashboard');
        }
    }

    public function resolve(Dashboard $dashboard, DashboardState $state, AnalyticsContext $context): DashboardView
    {
        $this->authorize($dashboard, $context);
        $request = new MetricRequest($state->range, $state->comparison, $state->filters, strict: false);
        $kpis = [];

        foreach ($dashboard->kpis() as $name) {
            $metric = $this->validator->lookupMetric($name, $dashboard);

            if ($metric === null || ! $this->visible($metric, $context, $dashboard)) {
                continue;
            }

            $kpis[] = $this->safely($name, fn () => $this->metrics->calculate($metric, $request, $context, $dashboard));
        }

        $charts = [];

        foreach ($dashboard->charts() as $chart) {
            $metric = $this->validator->lookupMetric($chart->metric(), $dashboard);

            if ($metric === null || ! $this->visible($metric, $context, $dashboard)) {
                continue;
            }

            try {
                $result = $this->metrics->calculate($metric, new MetricRequest($state->range, $state->comparison, $state->filters, $chart->interval(), $chart->groupBy(), $chart->limit(), false, $chart->compares()), $context, $dashboard);
                $charts[] = $this->charts->build($chart, $metric, $result);
            } catch (AnalyticsAuthorizationException) {
                continue;
            } catch (AnalyticsException $exception) {
                $charts[] = new ChartView($chart->nodeName(), $chart->label(), 'chartjs', [], '', [], [], true, $exception->getMessage());
            }
        }

        $reports = [];

        foreach ($dashboard->reports() as $section) {
            try {
                $report = $this->definitions->report($section->report());
            } catch (Throwable) {
                continue;
            }

            if (! $this->reports->allows($report, $context)) {
                continue;
            }

            $values = array_intersect_key($state->values, array_flip(array_map(static fn ($filter): string => $filter->nodeName(), $report->filters())));
            $reports[] = $this->safely($section->report(), fn () => $this->reports->run($report, new ReportRequest($values, $state->range, perPage: 10), $context));
        }

        return new DashboardView($dashboard->nodeName(), $dashboard->title(), $dashboard->description(), $state, $kpis, $charts, $reports, $this->filterOptions($dashboard, $context), $dashboard->refresh());
    }

    /**
     * Filter controls with the options the viewer may choose (option lists, or labelled keys of visible records).
     *
     * @return list<array{name: string, label: string, multiple: bool, options: array<string, string>}>
     */
    public function filterOptions(Dashboard $dashboard, AnalyticsContext $context): array
    {
        $controls = [];

        foreach ($dashboard->filters() as $filter) {
            $dimension = $this->dimensionFor($dashboard, $filter->nodeName());

            if ($dimension === null || ! $this->authorizer->dimension($dimension, $context)) {
                continue;
            }

            $options = $dimension->options();

            if ($options === [] && $dimension->dimensionType() === 'boolean') {
                $options = $this->labels->labels($dimension, ['1', '0'], $context);
            }

            $controls[] = ['name' => $filter->nodeName(), 'label' => $filter->label(), 'multiple' => $filter->isMultiple(), 'options' => $options];
        }

        return $controls;
    }

    private function dimensionFor(Dashboard $dashboard, string $name): ?Dimension
    {
        foreach ($dashboard->kpis() as $metricName) {
            $metric = $this->validator->lookupMetric($metricName, $dashboard);
            $dataset = $metric === null ? null : $this->datasetOf($metric);

            if ($dataset?->dimension($name) !== null) {
                return $dataset->dimension($name);
            }
        }

        foreach ($dashboard->charts() as $chart) {
            $metric = $this->validator->lookupMetric($chart->metric(), $dashboard);
            $dataset = $metric === null ? null : $this->datasetOf($metric);

            if ($dataset?->dimension($name) !== null) {
                return $dataset->dimension($name);
            }
        }

        return null;
    }

    private function datasetOf(Metric $metric): ?Dataset
    {
        try {
            return $this->definitions->datasetOf($metric);
        } catch (Throwable) {
            return null;
        }
    }

    private function visible(Metric $metric, AnalyticsContext $context, Dashboard $dashboard): bool
    {
        if ($metric->kind()->isComposite()) {
            foreach ($metric->dependencies() as $dependency) {
                $used = $this->validator->lookupMetric($dependency, $dashboard);

                if ($used === null || ! $this->visible($used, $context, $dashboard)) {
                    return false;
                }
            }

            return $this->authorizer->metric($metric, null, $context);
        }

        return $this->authorizer->metric($metric, $this->datasetOf($metric), $context);
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return array{name: string, result: T|null, error: string|null}
     */
    private function safely(string $name, \Closure $callback): array
    {
        try {
            return ['name' => $name, 'result' => $callback(), 'error' => null];
        } catch (AnalyticsAuthorizationException $exception) {
            return ['name' => $name, 'result' => null, 'error' => (string) __('gcego-analytics::messages.errors.forbidden')];
        } catch (AnalyticsException $exception) {
            return ['name' => $name, 'result' => null, 'error' => $exception->getMessage()];
        }
    }
}
