<?php

declare(strict_types=1);

namespace Gcego\Widgets\Charts;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Widgets\Data\WidgetDataResult;
use Gcego\Widgets\Rendering\WidgetRenderFrame;
use Gcego\Widgets\Support\KpiFormat;
use Gcego\Widgets\Widget;
use Gcego\Widgets\WidgetsConfig;

/**
 * A chart (line, bar, area, pie, doughnut), drawn by the configured chart
 * adapter (Chart.js, loaded lazily) from validated data:
 *
 *     new Chart(new Property(Chart::Name, 'student_enrolment'), new Property(Chart::Label, 'Student Enrolment'),
 *         new Property(Chart::Type, 'line'), new DataProvider(StudentEnrolmentProvider::class))
 *     new BarChart('Students per level', new Aggregate(Student::class, groupBy: 'level'))
 *     new AreaChart('Payments', new Aggregate(Payment::class, 'sum', 'amount', date: 'paid_at', bucket: 'month'), new DateRange(default: 'this_year'))
 *
 * Every chart also renders a text summary and its data as an accessible
 * table, so nothing depends on seeing the chart.
 */
class Chart extends Widget
{
    public const string Type = 'type';

    public const string Title = 'label';

    /** Bar charts: horizontal bars. */
    public const string Horizontal = 'horizontal';

    public const string Stacked = 'stacked';

    /** Show the legend (default: with several datasets, and for pie/doughnut). */
    public const string Legend = 'legend';

    /** A fixed summary instead of the generated one. */
    public const string Summary = 'summary';

    public const string Format = KpiFormat::Format;

    public const string Decimals = KpiFormat::Decimals;

    public const string Currency = KpiFormat::Currency;

    public const array Types = ['line', 'bar', 'area', 'pie', 'doughnut'];

    public function view(): string
    {
        return 'gcego-widgets::charts.chart';
    }

    public function chartType(): string
    {
        $type = $this->get(self::Type);

        return is_string($type) && in_array($type, self::Types, true) ? $type : static::defaultType();
    }

    public function isCircular(): bool
    {
        return in_array($this->chartType(), ['pie', 'doughnut'], true);
    }

    public function showsLegend(ChartData $data): bool
    {
        $legend = $this->get(self::Legend);

        return is_bool($legend) ? $legend : ($this->isCircular() || count($data->datasets) > 1);
    }

    public function formatValue(int|float|null $value, string $defaultCurrency): string
    {
        $decimals = $this->get(self::Decimals);

        return KpiFormat::format($value, $this->stringProperty(self::Format) ?? 'number', is_int($decimals) ? $decimals : null, $this->stringProperty(self::Currency) ?? $defaultCurrency) ?? '—';
    }

    public function viewData(WidgetDataResult $result, WidgetsConfig $config, WidgetRenderFrame $frame): array
    {
        $data = app(ChartDataNormalizer::class)->normalize($this, $result);
        $adapter = app(ChartAdapters::class)->get($config->chartAdapter);
        $rows = [];

        foreach ($data->labels as $index => $label) {
            $rows[] = ['label' => $label, 'values' => array_map(fn (ChartDataset $dataset): string => $this->formatValue($dataset->data[$index] ?? null, $config->currency), $data->datasets)];
        }

        return [
            'data' => $data,
            'config' => $adapter->config($this, $data),
            'module' => $adapter->module(),
            'rows' => $rows,
            'summary' => $this->summarize($data, $config),
            'empty' => $data->isEmpty(),
        ];
    }

    /**
     * A one-sentence text alternative: the series, their range, total and latest point.
     */
    public function summarize(ChartData $data, WidgetsConfig $config): string
    {
        $summary = $this->get(self::Summary);

        if (is_string($summary)) {
            return (string) __($summary);
        }

        if ($data->isEmpty()) {
            return (string) __('gcego-widgets::messages.no_points');
        }

        $parts = [];

        foreach ($data->datasets as $dataset) {
            $values = array_values(array_filter($dataset->data, static fn (mixed $value): bool => $value !== null));

            if ($values === []) {
                continue;
            }

            $max = max($values);
            $min = min($values);
            $maxLabel = $data->labels[array_search($max, $dataset->data, true)] ?? '';
            $minLabel = $data->labels[array_search($min, $dataset->data, true)] ?? '';
            $parts[] = (string) __($this->isCircular() ? 'gcego-widgets::messages.summary_share' : 'gcego-widgets::messages.summary_series', [
                'series' => $dataset->label,
                'points' => count($values),
                'total' => $this->formatValue(array_sum($values), $config->currency),
                'max' => $this->formatValue($max, $config->currency),
                'max_label' => $maxLabel,
                'min' => $this->formatValue($min, $config->currency),
                'min_label' => $minLabel,
            ]);
        }

        return implode(' ', $parts);
    }

    protected static function defaultType(): string
    {
        return 'line';
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Type, Type::string(), default: static::defaultType(), allowedValues: self::Types),
            new PropertyDefinition(self::Horizontal, Type::bool(), default: false),
            new PropertyDefinition(self::Stacked, Type::bool(), default: false),
            new PropertyDefinition(self::Legend, Type::bool()),
            new PropertyDefinition(self::Summary, Type::string()),
            ...KpiFormat::properties(),
        );
    }
}
