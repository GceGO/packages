<?php

declare(strict_types=1);

namespace Gcego\Widgets\Charts;

/**
 * A area chart: new AreaChart('Title', $dataSource, ...). See Chart.
 */
final class AreaChart extends Chart
{
    protected static function defaultType(): string
    {
        return 'area';
    }
}
