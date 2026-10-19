<?php

declare(strict_types=1);

namespace Gcego\Widgets\Charts;

/**
 * A line chart: new LineChart('Title', $dataSource, ...). See Chart.
 */
final class LineChart extends Chart
{
    protected static function defaultType(): string
    {
        return 'line';
    }
}
