<?php

declare(strict_types=1);

namespace Gcego\Pages\Tests\Fixtures\Providers;

use Gcego\Pages\Tests\Fixtures\Models\Subject;
use Gcego\Widgets\Contracts\WidgetDataProvider;
use Gcego\Widgets\Data\WidgetDataContext;
use Gcego\Widgets\Data\WidgetDataResult;

final class SubjectCount implements WidgetDataProvider
{
    public static int $calls = 0;

    public function resolve(WidgetDataContext $context): WidgetDataResult
    {
        self::$calls++;

        return WidgetDataResult::value($context->count(Subject::class));
    }
}
