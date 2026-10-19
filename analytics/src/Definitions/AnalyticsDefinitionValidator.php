<?php

declare(strict_types=1);

namespace Gcego\Analytics\Definitions;

use Gcego\Analytics\Dashboard;
use Gcego\Analytics\Datasets\Dataset;
use Gcego\Analytics\Exceptions\AnalyticsNotFoundException;
use Gcego\Analytics\Metrics\Metric;
use Gcego\Analytics\Registry\AnalyticsDefinitions;
use Gcego\Analytics\Registry\AnalyticsKind;
use Gcego\Analytics\Reports\Report;
use Gcego\Analytics\Reports\ReportFilter;

/**
 * Checks what single definitions cannot: references between them.
 *
 * - metrics: their dataset exists, the field is declared and allows the
 *   aggregate, filters name declared fields or dimensions, time series
 *   have a date column; ratio, percentage, rate and derived metrics name
 *   existing metrics without cycles;
 * - reports: the dataset exists, dimension columns are dimensions,
 *   aggregate columns use allowed fields, filters are filterable,
 *   drill-downs name reports;
 * - dashboards: KPIs and charts name metrics (inline or registered),
 *   group-by dimensions exist, report sections name reports.
 *
 * Problems are actionable sentences; the inspector lists them all and
 * resolving a broken definition refuses it.
 */
final readonly class AnalyticsDefinitionValidator
{
    public function __construct(private AnalyticsDefinitions $definitions) {}

    /**
     * @return list<string>
     */
    public function metric(Metric $metric, ?Dashboard $dashboard = null): array
    {
        $problems = [];

        if ($metric->kind()->isComposite()) {
            foreach ($metric->dependencies() as $dependency) {
                if ($this->lookupMetric($dependency, $dashboard) === null) {
                    $problems[] = sprintf('metric "%s" uses the unknown metric "%s".', $metric->nodeName(), $dependency);
                }
            }

            $cycle = $this->cycle($metric, $dashboard, []);

            if ($cycle !== null) {
                $problems[] = sprintf('metric "%s" depends on itself: %s.', $metric->nodeName(), implode(' → ', $cycle));
            }

            return $problems;
        }

        try {
            $dataset = $this->definitions->datasetOf($metric);
        } catch (AnalyticsNotFoundException) {
            return [sprintf('metric "%s" reads the unknown dataset "%s".', $metric->nodeName(), $metric->datasetName())];
        }

        if ($dataset === null) {
            return [sprintf('metric "%s" has no dataset.', $metric->nodeName())];
        }

        $aggregate = $metric->kind()->aggregate();
        $field = $metric->field();

        if ($aggregate !== null && $field !== null) {
            $declared = $dataset->field($field);

            if ($declared === null) {
                $problems[] = sprintf('metric "%s" measures "%s", which dataset "%s" does not declare as a Field.', $metric->nodeName(), $field, $dataset->nodeName());
            } elseif (! $declared->allows($aggregate)) {
                $problems[] = sprintf('metric "%s": the field "%s" does not allow %s.', $metric->nodeName(), $field, $aggregate->value);
            }
        }

        foreach (array_keys($metric->filters()) as $filter) {
            if ($dataset->field($filter) === null && $dataset->dimension($filter) === null) {
                $problems[] = sprintf('metric "%s" filters on "%s", which dataset "%s" does not declare.', $metric->nodeName(), $filter, $dataset->nodeName());
            }
        }

        if ($metric->interval() !== null && ($metric->dateColumn() ?? $dataset->dateColumn()) === null) {
            $problems[] = sprintf('metric "%s" is a time series but neither it nor dataset "%s" has a DateColumn.', $metric->nodeName(), $dataset->nodeName());
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    public function report(Report $report): array
    {
        try {
            $dataset = $this->definitions->dataset($report->datasetName());
        } catch (AnalyticsNotFoundException) {
            return [sprintf('report "%s" reads the unknown dataset "%s".', $report->nodeName(), $report->datasetName())];
        }

        $problems = [];

        foreach ($report->columns() as $column) {
            if (! $column->isMeasure()) {
                if ($dataset->dimension($column->dimension()) === null) {
                    $problems[] = sprintf('report "%s": the column "%s" groups by "%s", which is not a Dimension of dataset "%s".', $report->nodeName(), $column->nodeName(), $column->dimension(), $dataset->nodeName());
                }
            } elseif (($field = $column->field()) !== null && $column->aggregate() !== null) {
                $declared = $dataset->field($field);

                if ($declared === null || ! $declared->allows($column->aggregate())) {
                    $problems[] = sprintf('report "%s": the column "%s" needs a Field "%s" that allows %s.', $report->nodeName(), $column->nodeName(), $field, $column->aggregate()->value);
                }
            }

            if (($target = $column->drillDown()) !== null && ! $this->definitions->has(AnalyticsKind::Report, $target)) {
                $problems[] = sprintf('report "%s": the column "%s" drills down to the unknown report "%s".', $report->nodeName(), $column->nodeName(), $target);
            }
        }

        foreach ($report->filters() as $filter) {
            $problems = [...$problems, ...$this->filter($filter, $dataset, 'report "'.$report->nodeName().'"')];
        }

        if (($date = $report->dateColumn() ?? $dataset->dateColumn()) === null && $report->defaultRange() !== null) {
            $problems[] = sprintf('report "%s" has a DefaultRange but neither it nor dataset "%s" has a DateColumn.', $report->nodeName(), $dataset->nodeName());
        } elseif ($date !== null && ($field = $dataset->field($date)) !== null && ! $field->fieldType()->isTemporal()) {
            $problems[] = sprintf('report "%s": the date column "%s" is not a date field.', $report->nodeName(), $date);
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    public function dashboard(Dashboard $dashboard): array
    {
        $problems = [];

        foreach ($dashboard->metrics() as $metric) {
            $problems = [...$problems, ...$this->metric($metric, $dashboard)];
        }

        foreach ($dashboard->kpis() as $name) {
            if ($this->lookupMetric($name, $dashboard) === null) {
                $problems[] = sprintf('dashboard "%s" shows the unknown metric "%s".', $dashboard->nodeName(), $name);
            }
        }

        foreach ($dashboard->charts() as $chart) {
            $metric = $this->lookupMetric($chart->metric(), $dashboard);

            if ($metric === null) {
                $problems[] = sprintf('dashboard "%s": the chart "%s" shows the unknown metric "%s".', $dashboard->nodeName(), $chart->nodeName(), $chart->metric());

                continue;
            }

            if (($group = $chart->groupBy()) !== null) {
                $dataset = $this->datasetOrNull($metric);

                if ($metric->kind()->isComposite() || $dataset?->dimension($group) === null) {
                    $problems[] = sprintf('dashboard "%s": the chart "%s" groups by "%s", which is not a Dimension of its metric\'s dataset.', $dashboard->nodeName(), $chart->nodeName(), $group);
                }
            }
        }

        foreach ($dashboard->reports() as $section) {
            if (! $this->definitions->has(AnalyticsKind::Report, $section->report())) {
                $problems[] = sprintf('dashboard "%s" shows the unknown report "%s".', $dashboard->nodeName(), $section->report());
            }
        }

        return $problems;
    }

    public function lookupMetric(string $name, ?Dashboard $dashboard): ?Metric
    {
        $inline = $dashboard?->metric($name);

        if ($inline !== null) {
            return $inline;
        }

        try {
            return $this->definitions->metric($name);
        } catch (AnalyticsNotFoundException) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function filter(ReportFilter $filter, Dataset $dataset, string $owner): array
    {
        $field = $dataset->field($filter->target());
        $dimension = $dataset->dimension($filter->target());

        if ($field === null && $dimension === null) {
            return [sprintf('%s filters on "%s", which dataset "%s" does not declare.', $owner, $filter->target(), $dataset->nodeName())];
        }

        if (($field !== null && (! $field->isFilterable() || $field->isSensitive())) || ($dimension !== null && ! $dimension->isFilterable())) {
            return [sprintf('%s filters on "%s", which is not filterable.', $owner, $filter->target())];
        }

        return [];
    }

    private function datasetOrNull(Metric $metric): ?Dataset
    {
        try {
            return $this->definitions->datasetOf($metric);
        } catch (AnalyticsNotFoundException) {
            return null;
        }
    }

    /**
     * @param  list<string>  $path
     * @return list<string>|null
     */
    private function cycle(Metric $metric, ?Dashboard $dashboard, array $path): ?array
    {
        $path[] = $metric->nodeName();

        foreach ($metric->dependencies() as $dependency) {
            if (in_array($dependency, $path, true)) {
                return [...$path, $dependency];
            }

            $next = $this->lookupMetric($dependency, $dashboard);

            if ($next !== null && count($path) < 20 && ($found = $this->cycle($next, $dashboard, $path)) !== null) {
                return $found;
            }
        }

        return null;
    }
}
