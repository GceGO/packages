<?php

declare(strict_types=1);

namespace Gcego\Widgets\Charts;

use Gcego\Widgets\Data\WidgetDataResult;
use Gcego\Widgets\Exceptions\WidgetDataException;
use Gcego\Widgets\WidgetsConfig;

/**
 * Validates provider data for a chart before anything reaches the browser:
 *
 * - labels become strings (at most 80 characters) and at most charts.max_points;
 * - at most charts.max_datasets datasets, each with one value per label;
 * - values are finite numbers or null (gaps); numeric strings are converted;
 *   anything else is refused;
 * - pie and doughnut charts take exactly one dataset of non-negative values;
 * - tones come from a fixed list (primary, success, warning, danger, info,
 *   neutral), assigned in order when missing.
 *
 * Invalid data is a WidgetDataException (the widget shows its error state),
 * never a partially drawn chart.
 */
final readonly class ChartDataNormalizer
{
    public function __construct(private WidgetsConfig $config) {}

    public function normalize(Chart $chart, WidgetDataResult $result): ChartData
    {
        $name = $chart->widgetName();
        $labels = [];

        foreach ($result->labels as $label) {
            if (! is_scalar($label)) {
                throw WidgetDataException::invalid($name, 'labels must be text.');
            }

            $labels[] = mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $label)), 0, 80);
        }

        if (count($labels) > $this->config->maxChartPoints) {
            throw WidgetDataException::invalid($name, sprintf('a chart takes at most %d points.', $this->config->maxChartPoints));
        }

        if (count($result->datasets) > $this->config->maxDatasets) {
            throw WidgetDataException::invalid($name, sprintf('a chart takes at most %d datasets.', $this->config->maxDatasets));
        }

        if ($chart->isCircular() && count($result->datasets) > 1) {
            throw WidgetDataException::invalid($name, 'pie and doughnut charts take one dataset.');
        }

        $datasets = [];

        foreach (array_values($result->datasets) as $index => $dataset) {
            if (! $dataset instanceof ChartDataset) {
                throw WidgetDataException::invalid($name, 'datasets must be ChartDataset objects.');
            }

            if (count($dataset->data) !== count($labels)) {
                throw WidgetDataException::invalid($name, sprintf('dataset "%s" needs one value per label.', mb_substr($dataset->label, 0, 40)));
            }

            $values = [];

            foreach ($dataset->data as $value) {
                $values[] = $this->number($name, $value, $chart->isCircular());
            }

            $tone = $dataset->tone !== null && in_array($dataset->tone, ChartDataset::Tones, true) ? $dataset->tone : ChartDataset::Tones[$index % count(ChartDataset::Tones)];
            $datasets[] = new ChartDataset(mb_substr(trim($dataset->label), 0, 80), $values, $tone);
        }

        return new ChartData($labels, $datasets);
    }

    private function number(string $name, mixed $value, bool $circular): int|float|null
    {
        $number = match (true) {
            $value === null => null,
            is_int($value) => $value,
            is_float($value) && is_finite($value) => $value,
            is_string($value) && is_numeric($value) => $value + 0,
            default => throw WidgetDataException::invalid($name, 'chart values must be numbers or null.'),
        };

        if ($circular && $number !== null && $number < 0) {
            throw WidgetDataException::invalid($name, 'pie and doughnut values cannot be negative.');
        }

        return $number;
    }
}
