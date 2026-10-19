<?php

declare(strict_types=1);

namespace Gcego\Analytics\Integration\Widgets;

use Gcego\Analytics\Exceptions\AnalyticsException;
use Gcego\Analytics\Filters\DateRangePreset;
use Gcego\Analytics\TimeSeries\TimeInterval;
use Gcego\Widgets\Data\WidgetDataContext;
use Gcego\Widgets\Data\WidgetDataResult;

/**
 * A gcego/widgets chart series from a time-series metric:
 *
 *     final class RegistrationsTrend extends MetricTrend
 *     {
 *         protected function metric(): string { return 'daily_registrations'; }
 *     }
 */
abstract class MetricTrend extends MetricValue
{
    protected function interval(): TimeInterval
    {
        return TimeInterval::Day;
    }

    public function resolve(WidgetDataContext $context): WidgetDataResult
    {
        try {
            $result = $this->analytics($context)->metric($this->metric())->calculate(range: $this->range() ?? DateRangePreset::Last30Days, interval: $this->interval());
        } catch (AnalyticsException) {
            return WidgetDataResult::empty();
        }

        $points = [];

        foreach ($result->series as $point) {
            $points[$point['label']] = $point['value'] ?? 0;
        }

        return WidgetDataResult::series($result->label, $points);
    }
}
