<?php

declare(strict_types=1);

namespace Gcego\Layouts\Tests\Fixtures\Providers;

use Gcego\Layouts\Tests\Fixtures\Models\Student;
use Gcego\Widgets\Contracts\WidgetDataProvider;
use Gcego\Widgets\Data\WidgetDataContext;
use Gcego\Widgets\Data\WidgetDataResult;

/**
 * Counts students through gcego/data (policies and tenant scopes apply).
 */
final class CountProvider implements WidgetDataProvider
{
    public function resolve(WidgetDataContext $context): WidgetDataResult
    {
        return WidgetDataResult::value($context->count(Student::class));
    }
}
