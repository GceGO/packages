<?php

declare(strict_types=1);

namespace Gcego\Widgets\Tests\Fixtures\Providers;

use Gcego\Widgets\Contracts\WidgetDataProvider;
use Gcego\Widgets\Data\WidgetDataContext;
use Gcego\Widgets\Data\WidgetDataResult;
use RuntimeException;

final class FailingProvider implements WidgetDataProvider
{
    public function resolve(WidgetDataContext $context): WidgetDataResult
    {
        throw new RuntimeException('SQLSTATE[42S02]: select secret_column from salaries');
    }
}
