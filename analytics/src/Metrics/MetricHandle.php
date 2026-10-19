<?php

declare(strict_types=1);

namespace Gcego\Analytics\Metrics;

use Gcego\Analytics\Comparisons\Comparison;
use Gcego\Analytics\Comparisons\ComparisonMode;
use Gcego\Analytics\Exceptions\AnalyticsQueryException;
use Gcego\Analytics\Filters\AnalyticsFilter;
use Gcego\Analytics\Filters\DateRange;
use Gcego\Analytics\Filters\DateRangePreset;
use Gcego\Analytics\Filters\FilterOperator;
use Gcego\Analytics\Security\AnalyticsContext;
use Gcego\Analytics\TimeSeries\TimeInterval;

/**
 * A registered metric, ready to calculate for a context:
 *
 *     $analytics->metric('total_students')->calculate();
 *     $analytics->metric('daily_registrations')->calculate(range: 'last_30_days', interval: 'day', comparison: 'previous_period');
 *     $analytics->metric('average_score')->calculate(range: DateRange::between('2026-01-01', '2026-03-31', 'Africa/Douala'), filters: ['level' => 'o'], groupBy: 'subject_id');
 *
 * Filter values are untrusted: only filterable fields and dimensions of
 * the metric's dataset, validated by the planner.
 */
final readonly class MetricHandle
{
    public function __construct(private Metric $metric, private MetricCalculator $calculator, private AnalyticsContext $context) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function calculate(
        DateRange|DateRangePreset|string|null $range = null,
        Comparison|ComparisonMode|string|null $comparison = null,
        array $filters = [],
        TimeInterval|string|null $interval = null,
        ?string $groupBy = null,
        int $limit = 12,
        bool $compareSeries = false,
    ): MetricResult {
        $range = is_string($range) || $range instanceof DateRangePreset ? DateRange::preset($range, $this->context->timezone) : $range;
        $interval = is_string($interval) ? (TimeInterval::tryFrom($interval) ?? throw AnalyticsQueryException::rejected('interval', 'Unknown interval.')) : ($interval ?? ($range !== null ? $this->metric->interval() : null));
        $mode = is_string($comparison) ? (ComparisonMode::tryFrom($comparison) ?? throw AnalyticsQueryException::rejected('comparison', 'Unknown comparison.')) : $comparison;
        $comparison = $mode instanceof ComparisonMode ? ($mode === ComparisonMode::None ? null : new Comparison($mode)) : $mode;

        if ($limit < 1 || $limit > 50) {
            throw AnalyticsQueryException::rejected('limit', 'Show between 1 and 50 groups.');
        }

        $conditions = [];

        foreach ($filters as $target => $value) {
            if (! is_string($target) || ! (is_scalar($value) || is_array($value))) {
                throw AnalyticsQueryException::rejected('filter', 'Filters are field => value pairs.');
            }

            $list = is_array($value) ? array_values(array_filter($value, is_scalar(...))) : $value;
            $conditions[] = new AnalyticsFilter($target, is_array($list) ? FilterOperator::In : FilterOperator::Equals, $list);
        }

        return $this->calculator->calculate($this->metric, new MetricRequest($range, $comparison, $conditions, $interval, $groupBy, $limit, true, $compareSeries), $this->context);
    }

    public function definition(): Metric
    {
        return $this->metric;
    }
}
