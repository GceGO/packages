<?php

declare(strict_types=1);

namespace Gcego\Widgets\Charts;

/**
 * A pie chart: new PieChart('Title', $dataSource, ...). See Chart.
 */
final class PieChart extends Chart
{
    protected static function defaultType(): string
    {
        return 'pie';
    }
}
