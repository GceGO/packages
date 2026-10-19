<?php

declare(strict_types=1);

namespace Gcego\Widgets\Tests\Fixtures\Providers;

use Gcego\Widgets\Contracts\WidgetDataProvider;
use Gcego\Widgets\Data\WidgetDataContext;
use Gcego\Widgets\Data\WidgetDataResult;
use Gcego\Widgets\Tests\Fixtures\Models\Student;

final class StudentCountProvider implements WidgetDataProvider
{
    public static int $calls = 0;

    public function resolve(WidgetDataContext $context): WidgetDataResult
    {
        self::$calls++;

        return WidgetDataResult::value($context->count(Student::class));
    }
}
