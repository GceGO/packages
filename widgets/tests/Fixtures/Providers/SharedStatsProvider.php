<?php

declare(strict_types=1);

namespace Gcego\Widgets\Tests\Fixtures\Providers;

use Gcego\Widgets\Contracts\WidgetDataProvider;
use Gcego\Widgets\Data\WidgetDataContext;
use Gcego\Widgets\Data\WidgetDataResult;

final class SharedStatsProvider implements WidgetDataProvider
{
    public function resolve(WidgetDataContext $context): WidgetDataResult
    {
        return WidgetDataResult::values(['rooms' => 12, 'buses' => WidgetDataResult::value(4, previous: 5)]);
    }
}
