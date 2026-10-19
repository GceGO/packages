<?php

declare(strict_types=1);

namespace Gcego\Widgets\Charts;

/**
 * A doughnut chart: new DoughnutChart('Title', $dataSource, ...). See Chart.
 */
final class DoughnutChart extends Chart
{
    protected static function defaultType(): string
    {
        return 'doughnut';
    }
}
