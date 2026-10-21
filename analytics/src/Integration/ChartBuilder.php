<?php

declare(strict_types=1);

namespace Gcego\Analytics\Integration;

use Gcego\Analytics\Dashboards\Chart;
use Gcego\Analytics\Dashboards\ChartView;
use Gcego\Analytics\Metrics\Metric;
use Gcego\Analytics\Metrics\MetricResult;
use Gcego\Analytics\Support\Format;
use Gcego\Components\Properties\Property;
use Gcego\Widgets\Charts\AreaChart;
use Gcego\Widgets\Charts\BarChart;
use Gcego\Widgets\Charts\Chart as WidgetChart;
use Gcego\Widgets\Charts\ChartAdapters;
use Gcego\Widgets\Charts\ChartData;
use Gcego\Widgets\Charts\ChartDataset;
use Gcego\Widgets\Charts\DoughnutChart;
use Gcego\Widgets\Charts\LineChart;
use Gcego\Widgets\Charts\PieChart;
use Gcego\Widgets\Widget;

/**
 * Turns metric results into gcego/widgets chart configurations (its
 * Chart.js adapter and Alpine "gcegoChart" script render them; analytics
 * ships no chart code), with a summary sentence and the data as table rows.
 */
final readonly class ChartBuilder
{
    public function __construct(private ChartAdapters $adapters) {}

    public function build(Chart $chart, Metric $metric, MetricResult $result): ChartView
    {
        $series = $chart->groupBy() !== null ? $result->groups : $result->series;
        $labels = array_map(static fn (array $point): string => $point['label'], $series);
        $datasets = [new ChartDataset($metric->label(), array_map(static fn (array $point): int|float|null => $point['value'], $series), 'primary')];
        $headers = [$metric->label()];

        if ($result->comparisonSeries !== [] && $chart->groupBy() === null) {
            $previous = array_map(static fn (array $point): int|float|null => $point['value'], array_slice($result->comparisonSeries, 0, count($series)));
            $datasets[] = new ChartDataset((string) __('gcego-analytics::messages.comparison_series'), array_pad($previous, count($series), null), 'neutral');
            $headers[] = (string) __('gcego-analytics::messages.comparison_series');
        }

        $data = new ChartData($labels, $datasets);
        $widget = $this->widget($chart, $metric);
        $adapter = $this->adapters->get('chartjs');
        $rows = [];

        foreach ($series as $index => $point) {
            $values = [$point['formatted']];

            if (isset($result->comparisonSeries[$index])) {
                $values[] = $result->comparisonSeries[$index]['formatted'];
            }

            $rows[] = ['label' => $point['label'], 'values' => $values];
        }

        return new ChartView($chart->nodeName(), $chart->label(), $adapter->module(), $adapter->config($widget, $data), $this->summary($chart, $metric, $result, $series), $headers, $rows, $data->isEmpty());
    }

    /**
     * @param  list<array{key: string|null, label: string, value: int|float|null, formatted: string, suppressed: bool}>  $series
     */
    private function summary(Chart $chart, Metric $metric, MetricResult $result, array $series): string
    {
        $values = array_filter($series, static fn (array $point): bool => $point['value'] !== null);

        if ($values === []) {
            return (string) __('gcego-analytics::messages.chart_empty', ['chart' => $chart->label()]);
        }

        usort($values, static fn (array $a, array $b): int => $b['value'] <=> $a['value']);
        $highest = $values[0];
        $lowest = $values[array_key_last($values)];

        return (string) __('gcego-analytics::messages.chart_summary', [
            'chart' => $chart->label(), 'count' => count($series), 'metric' => $metric->label(),
            'highest' => $highest['label'], 'highest_value' => $highest['formatted'], 'lowest' => $lowest['label'], 'lowest_value' => $lowest['formatted'],
        ]);
    }

    private function widget(Chart $chart, Metric $metric): WidgetChart
    {
        $properties = [
            new Property(Widget::Name, str_replace('-', '_', $chart->nodeName())),
            new Property(WidgetChart::Title, $chart->label()),
            // Percentages are already 0–100 (the browser's "percent" style would multiply them again).
            new Property(WidgetChart::Format, $metric->format() === Format::Currency ? 'currency' : 'number'),
            new Property(WidgetChart::Decimals, $metric->precision()),
        ];

        return match ($chart->chartType()) {
            'bar' => new BarChart(...$properties),
            'area' => new AreaChart(...$properties),
            'pie' => new PieChart(...$properties),
            'doughnut' => new DoughnutChart(...$properties),
            default => new LineChart(...$properties),
        };
    }
}
