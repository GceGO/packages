<?php

declare(strict_types=1);

namespace Gcego\Widgets\Tests\Fixtures\Providers;

use Gcego\Data\Aggregation\Aggregation;
use Gcego\Data\Filters\Filter;
use Gcego\Data\Source;
use Gcego\Widgets\Charts\ChartDataset;
use Gcego\Widgets\Contracts\WidgetDataProvider;
use Gcego\Widgets\Data\WidgetDataContext;
use Gcego\Widgets\Data\WidgetDataResult;
use Gcego\Widgets\Tests\Fixtures\Models\Student;

final class EnrolmentProvider implements WidgetDataProvider
{
    public function resolve(WidgetDataContext $context): WidgetDataResult
    {
        $boys = $context->aggregate(new Source(Student::class, new Filter('gender', 'm')), new Aggregation(groupBy: 'level'))->pairs();
        $girls = $context->aggregate(new Source(Student::class, new Filter('gender', 'f')), new Aggregation(groupBy: 'level'))->pairs();
        $levels = array_values(array_unique([...array_keys($boys), ...array_keys($girls)]));
        sort($levels);

        return WidgetDataResult::chart(
            array_map(static fn (int|string $level): string => 'Level '.$level, $levels),
            [
                new ChartDataset('Boys', array_map(static fn (int|string $level): int|float => $boys[$level] ?? 0, $levels), 'info'),
                new ChartDataset('Girls', array_map(static fn (int|string $level): int|float => $girls[$level] ?? 0, $levels), 'success'),
            ],
        );
    }
}
