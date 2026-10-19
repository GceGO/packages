<?php

declare(strict_types=1);

namespace Gcego\Widgets\Charts;

use Gcego\Widgets\Contracts\ChartAdapter;
use Gcego\Widgets\WidgetsConfig;

/**
 * The default chart adapter (Chart.js 4, see resources/js/charts). It emits
 * a small allowlisted description, not a Chart.js configuration: the
 * browser module builds the real configuration from it, so no callbacks,
 * HTML or options from the server (let alone users) can reach Chart.js.
 */
final readonly class ChartJsAdapter implements ChartAdapter
{
    public function __construct(private WidgetsConfig $config) {}

    public function id(): string
    {
        return 'chartjs';
    }

    public function module(): string
    {
        return 'chartjs';
    }

    public function config(Chart $chart, ChartData $data): array
    {
        $type = $chart->chartType();

        return [
            'type' => $type,
            'labels' => $data->labels,
            'datasets' => array_map(static fn (ChartDataset $dataset): array => ['label' => $dataset->label, 'data' => $dataset->data, 'tone' => $dataset->tone], $data->datasets),
            'horizontal' => $type === 'bar' && $chart->get(Chart::Horizontal) === true,
            'stacked' => $chart->get(Chart::Stacked) === true,
            'legend' => $chart->showsLegend($data),
            'format' => [
                'style' => is_string($format = $chart->get(Chart::Format)) ? $format : 'number',
                'currency' => is_string($currency = $chart->get(Chart::Currency)) ? $currency : $this->config->currency,
                'decimals' => is_int($decimals = $chart->get(Chart::Decimals)) ? $decimals : null,
                'locale' => str_replace('_', '-', app()->getLocale()),
            ],
        ];
    }
}
