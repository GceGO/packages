<?php

declare(strict_types=1);

namespace Gcego\Widgets\Charts;

/**
 * A bar chart: new BarChart('Title', $dataSource, ...). See Chart.
 */
final class BarChart extends Chart
{
    protected static function defaultType(): string
    {
        return 'bar';
    }
}
