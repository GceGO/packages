<?php

declare(strict_types=1);

namespace Gcego\Analytics\Integration\Widgets;

use Gcego\Analytics\AnalyticsManager;
use Gcego\Analytics\Exceptions\AnalyticsException;
use Gcego\Analytics\Filters\DateRangePreset;
use Gcego\Widgets\Contracts\WidgetDataProvider;
use Gcego\Widgets\Data\WidgetDataContext;
use Gcego\Widgets\Data\WidgetDataResult;

/**
 * A gcego/widgets KPI (Stat) from a metric, with the previous period for its trend:
 *
 *     final class ActiveStudentsValue extends MetricValue
 *     {
 *         protected function metric(): string { return 'active_students'; }
 *     }
 *
 *     new Stat(new Property(Stat::Name, 'active_students'), new Property(Stat::Label, 'Active students'), new DataProvider(ActiveStudentsValue::class))
 *
 * The analytics package calculates (authorized as the widget's viewer, in its tenant); widgets present.
 */
abstract class MetricValue implements WidgetDataProvider
{
    abstract protected function metric(): string;

    protected function range(): ?DateRangePreset
    {
        return DateRangePreset::Last30Days;
    }

    public function resolve(WidgetDataContext $context): WidgetDataResult
    {
        try {
            $result = $this->analytics($context)->metric($this->metric())->calculate(range: $this->range(), comparison: $this->range() === null ? null : 'previous_period');
        } catch (AnalyticsException) {
            return WidgetDataResult::empty();
        }

        $previous = $result->comparison?->previous;

        return $result->value === null ? WidgetDataResult::empty() : WidgetDataResult::value($result->value, is_int($previous) || is_float($previous) ? $previous : null);
    }

    protected function analytics(WidgetDataContext $context): AnalyticsManager
    {
        return app(AnalyticsManager::class)->as($context->user, $context->tenant === null ? null : (string) $context->tenant->key, $context->timezone);
    }
}
