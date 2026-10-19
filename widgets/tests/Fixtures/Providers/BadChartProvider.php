<?php

declare(strict_types=1);

namespace Gcego\Widgets\Tests\Fixtures\Providers;

use Gcego\Widgets\Charts\ChartDataset;
use Gcego\Widgets\Contracts\WidgetDataProvider;
use Gcego\Widgets\Data\WidgetDataContext;
use Gcego\Widgets\Data\WidgetDataResult;

final class BadChartProvider implements WidgetDataProvider
{
    public function resolve(WidgetDataContext $context): WidgetDataResult
    {
        return WidgetDataResult::chart(['a', 'b'], [new ChartDataset('x', [1, 'alert(1)'])]); // @phpstan-ignore argument.type
    }
}
