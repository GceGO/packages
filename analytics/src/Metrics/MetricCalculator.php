<?php

declare(strict_types=1);

namespace Gcego\Analytics\Metrics;

use Carbon\CarbonImmutable;
use Gcego\Analytics\AnalyticsConfig;
use Gcego\Analytics\Cache\AnalyticsCache;
use Gcego\Analytics\Comparisons\ComparisonResult;
use Gcego\Analytics\Dashboard;
use Gcego\Analytics\Datasets\Dataset;
use Gcego\Analytics\Definitions\AnalyticsDefinitionValidator;
use Gcego\Analytics\Dimensions\DimensionLabelResolver;
use Gcego\Analytics\Events\MetricCalculated;
use Gcego\Analytics\Exceptions\AnalyticsAuthorizationException;
use Gcego\Analytics\Exceptions\AnalyticsQueryException;
use Gcego\Analytics\Exceptions\InvalidDefinitionException;
use Gcego\Analytics\Filters\AnalyticsFilter;
use Gcego\Analytics\Filters\DateRange;
use Gcego\Analytics\Filters\FilterOperator;
use Gcego\Analytics\Measures\Aggregate;
use Gcego\Analytics\Measures\Measure;
use Gcego\Analytics\Queries\AnalyticsQuery;
use Gcego\Analytics\Queries\AnalyticsQueryPlanner;
use Gcego\Analytics\Queries\EloquentAnalyticsExecutor;
use Gcego\Analytics\Registry\AnalyticsDefinitions;
use Gcego\Analytics\Security\AnalyticsAuthorizer;
use Gcego\Analytics\Security\AnalyticsContext;
use Gcego\Analytics\Support\DefinitionHash;
use Gcego\Analytics\TimeSeries\FillStrategy;
use Gcego\Analytics\TimeSeries\TimeInterval;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Calculates metrics: a value over a period, optionally a time series
 * (gaps filled per the metric's fill strategy), groups by a dimension
 * (top N plus "Other" for counts and sums) and a comparison period.
 *
 * Composite metrics (ratio, percentage, rate, derived) calculate the
 * metrics they use in the same context and combine them in PHP with the
 * safe Formula evaluator; a missing operand or a zero denominator gives no
 * value. Every metric involved is authorized; results are cached per
 * audience (AnalyticsCache) before formatting.
 */
final readonly class MetricCalculator
{
    public function __construct(
        private AnalyticsDefinitions $definitions,
        private AnalyticsDefinitionValidator $validator,
        private AnalyticsQueryPlanner $planner,
        private EloquentAnalyticsExecutor $executor,
        private AnalyticsAuthorizer $authorizer,
        private DimensionLabelResolver $labels,
        private AnalyticsCache $cache,
        private AnalyticsConfig $config,
        private Dispatcher $events,
    ) {}

    public function calculate(Metric $metric, MetricRequest $request, AnalyticsContext $context, ?Dashboard $dashboard = null): MetricResult
    {
        $problems = $this->validator->metric($metric, $dashboard);

        if ($problems !== []) {
            throw InvalidDefinitionException::invalid('Metric "'.$metric->nodeName().'"', implode(' ', $problems));
        }

        if (! $this->allowed($metric, $context, $dashboard, [])) {
            throw AnalyticsAuthorizationException::for('see this metric');
        }

        if ($request->groupBy !== null && $metric->kind()->isComposite()) {
            throw AnalyticsQueryException::rejected('dimension', 'Composite metrics cannot be grouped.');
        }

        $started = hrtime(true);
        $hash = DefinitionHash::of($metric).':'.implode(',', array_map(fn (Metric $used): string => DefinitionHash::of($used).':'.DefinitionHash::of($this->datasetOf($used) ?? $used), $this->closure($metric, $dashboard)));
        [$raw, $cached] = $this->cache->remember('metric', ($dashboard === null ? '' : $dashboard->nodeName().'/').$metric->nodeName(), $hash, $this->datasetNames($metric, $dashboard), $request->key(), $context, $metric->cacheSeconds(), fn (): array => $this->compute($metric, $request, $context, $dashboard));
        $this->events->dispatch(new MetricCalculated($metric->nodeName(), $context->tenant, $cached, round((hrtime(true) - $started) / 1e6, 3), $context->correlation));

        return $this->result($metric, $raw, $cached, $request, $context);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function result(Metric $metric, array $raw, bool $cached, MetricRequest $request, AnalyticsContext $context): MetricResult
    {
        $format = static fn (int|float|null $value): string => $metric->format()->format($value, $metric->precision(), $metric->currency(), $metric->unit(), $context->timezone);
        $value = self::num($raw['value'] ?? null);
        $points = static function (mixed $series) use ($format, $request, $context): array {
            $result = [];

            foreach (is_array($series) ? $series : [] as $point) {
                if (is_array($point) && is_string($point['key'] ?? null)) {
                    $pointValue = self::num($point['value'] ?? null);
                    $result[] = ['key' => $point['key'], 'label' => $request->interval?->label($point['key'], $request->range->timezone ?? $context->timezone) ?? $point['key'], 'value' => $pointValue, 'formatted' => $format($pointValue), 'suppressed' => ($point['suppressed'] ?? false) === true];
                }
            }

            return $result;
        };

        $groups = [];

        foreach (is_array($raw['groups'] ?? null) ? $raw['groups'] : [] as $group) {
            if (is_array($group)) {
                $groupValue = self::num($group['value'] ?? null);
                $groups[] = ['key' => is_string($group['key'] ?? null) ? $group['key'] : null, 'label' => is_string($group['label'] ?? null) ? $group['label'] : '', 'value' => $groupValue, 'formatted' => ($group['suppressed'] ?? false) === true ? (string) __('gcego-analytics::messages.suppressed') : $format($groupValue), 'suppressed' => ($group['suppressed'] ?? false) === true];
            }
        }

        $range = ($raw['ranged'] ?? false) === true ? $request->range : null;
        $comparisonRange = $range === null ? null : $request->comparison?->rangeFor($range);
        $comparison = $comparisonRange === null || $request->comparison === null ? null : ComparisonResult::between($request->comparison->mode, $comparisonRange, $value, self::num($raw['previous'] ?? null), $metric->precision());

        return new MetricResult(
            metric: $metric->nodeName(),
            label: $metric->label(),
            value: $value,
            formatted: ($raw['suppressed'] ?? false) === true ? (string) __('gcego-analytics::messages.suppressed') : $format($value),
            format: $metric->format(),
            unit: $metric->unit(),
            precision: $metric->precision(),
            range: $range,
            comparison: $comparison,
            series: $points($raw['series'] ?? []),
            comparisonSeries: $points($raw['comparison_series'] ?? []),
            groups: $groups,
            suppressed: ($raw['suppressed'] ?? false) === true,
            truncated: ($raw['truncated'] ?? false) === true,
            calculatedAt: is_string($raw['at'] ?? null) ? CarbonImmutable::parse($raw['at']) : CarbonImmutable::now(),
            cached: $cached,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(Metric $metric, MetricRequest $request, AnalyticsContext $context, ?Dashboard $dashboard): array
    {
        [$value, $suppressed, $ranged] = $this->value($metric, $request, $request->range, $context, $dashboard, []);
        $raw = ['value' => $value, 'suppressed' => $suppressed, 'ranged' => $ranged, 'series' => [], 'comparison_series' => [], 'groups' => [], 'truncated' => false, 'previous' => null, 'at' => CarbonImmutable::now()->toIso8601String()];
        $comparisonRange = $ranged && $request->range !== null ? $request->comparison?->rangeFor($request->range) : null;

        if ($comparisonRange !== null) {
            $raw['previous'] = $this->value($metric, $request, $comparisonRange, $context, $dashboard, [])[0];
        }

        if ($request->interval !== null) {
            if ($request->range === null) {
                throw AnalyticsQueryException::rejected('date_range', 'A time series needs a period.');
            }

            $raw['series'] = $this->series($metric, $request, $request->range, $request->interval, $context, $dashboard, []);

            if ($request->compareSeries && $comparisonRange !== null) {
                $raw['comparison_series'] = $this->series($metric, $request, $comparisonRange, $request->interval, $context, $dashboard, []);
            }
        }

        if ($request->groupBy !== null) {
            [$raw['groups'], $raw['truncated']] = $this->groups($metric, $request, $context, is_int($value) || is_float($value) ? $value : null);
        }

        return $raw;
    }

    /**
     * @param  list<string>  $stack
     * @return array{0: int|float|null, 1: bool, 2: bool} value, suppressed, whether the period applied
     */
    private function value(Metric $metric, MetricRequest $request, ?DateRange $range, AnalyticsContext $context, ?Dashboard $dashboard, array $stack): array
    {
        if ($metric->kind()->isComposite()) {
            $values = [];
            $suppressed = false;
            $ranged = false;

            foreach ($metric->dependencies() as $dependency) {
                [$values[$dependency], $hidden, $applied] = $this->value($this->dependency($dependency, $dashboard, [...$stack, $metric->nodeName()]), $request, $range, $context, $dashboard, [...$stack, $metric->nodeName()]);
                $suppressed = $suppressed || $hidden;
                $ranged = $ranged || $applied;
            }

            return [$suppressed ? null : $this->combine($metric, $values), $suppressed, $ranged];
        }

        $dataset = $this->datasetOf($metric) ?? throw InvalidDefinitionException::invalid('Metric "'.$metric->nodeName().'"', 'it has no dataset.');
        $query = $this->query($metric, $dataset, $request, $range);
        $rows = $this->executor->execute($this->planner->plan($query, $context, $metric->model() === null ? null : $metric), $context);
        $value = $rows->first()['value'] ?? null;

        return [$rows->suppressed() ? null : $this->round($metric, $value), $rows->suppressed(), $query->range !== null];
    }

    /**
     * @param  list<string>  $stack
     * @return list<array{key: string, value: int|float|null, suppressed: bool}>
     */
    private function series(Metric $metric, MetricRequest $request, DateRange $range, TimeInterval $interval, AnalyticsContext $context, ?Dashboard $dashboard, array $stack): array
    {
        $keys = $interval->keys($range->start, $range->end(), $this->config->maxChartPoints);

        if ($metric->kind()->isComposite()) {
            /** @var array<string, array<string, int|float|null>> $parts */
            $parts = [];
            $hidden = [];

            foreach ($metric->dependencies() as $dependency) {
                foreach ($this->series($this->dependency($dependency, $dashboard, [...$stack, $metric->nodeName()]), $request, $range, $interval, $context, $dashboard, [...$stack, $metric->nodeName()]) as $point) {
                    $parts[$point['key']][$dependency] = $point['suppressed'] ? null : $point['value'];
                    $hidden[$point['key']] = ($hidden[$point['key']] ?? false) || $point['suppressed'];
                }
            }

            return array_map(fn (string $key): array => ['key' => $key, 'value' => ($hidden[$key] ?? false) ? null : $this->combine($metric, $parts[$key] ?? []), 'suppressed' => $hidden[$key] ?? false], $keys);
        }

        $dataset = $this->datasetOf($metric) ?? throw InvalidDefinitionException::invalid('Metric "'.$metric->nodeName().'"', 'it has no dataset.');
        $query = $this->query($metric, $dataset, $request, $range, $interval);
        $rows = $this->executor->execute($this->planner->plan($query, $context, $metric->model() === null ? null : $metric), $context);
        $observed = [];

        foreach ($rows->rows as $row) {
            if ($row['bucket'] !== null) {
                $observed[$row['bucket']] = $row;
            }
        }

        $fill = $metric->fill();
        $points = [];

        foreach ($keys as $key) {
            if (isset($observed[$key])) {
                $points[] = ['key' => $key, 'value' => $observed[$key]['suppressed'] ? null : $this->round($metric, $observed[$key]['values']['value'] ?? null), 'suppressed' => $observed[$key]['suppressed']];
            } elseif ($fill !== FillStrategy::None) {
                // Zero only where "nothing happened" is a true zero (counts, sums); otherwise no value.
                $points[] = ['key' => $key, 'value' => $fill === FillStrategy::Zero ? 0 : null, 'suppressed' => false];
            }
        }

        return $points;
    }

    /**
     * @return array{0: list<array{key: string|null, label: string, value: int|float|null, suppressed: bool}>, 1: bool}
     */
    private function groups(Metric $metric, MetricRequest $request, AnalyticsContext $context, int|float|null $total): array
    {
        $dataset = $this->datasetOf($metric) ?? throw InvalidDefinitionException::invalid('Metric "'.$metric->nodeName().'"', 'it has no dataset.');
        $dimension = $dataset->dimension((string) $request->groupBy) ?? throw AnalyticsQueryException::rejected('dimension', 'That dimension is not available.');
        $query = $this->query($metric, $dataset, $request, $request->range, null, [$dimension->nodeName()], ['value' => 'desc'], $request->limit);
        $rows = $this->executor->execute($this->planner->plan($query, $context, $metric->model() === null ? null : $metric), $context);
        $keys = array_map(static fn (array $row): ?string => $row['dimensions'][$dimension->nodeName()] ?? null, $rows->rows);
        $labels = $this->labels->labels($dimension, $keys, $context);
        $groups = [];
        $shown = 0;

        foreach ($rows->rows as $row) {
            $key = $row['dimensions'][$dimension->nodeName()] ?? null;
            $value = $row['suppressed'] ? null : $this->round($metric, $row['values']['value'] ?? null);
            $shown += is_int($value) || is_float($value) ? $value : 0;
            $groups[] = ['key' => $key, 'label' => $key === null ? DimensionLabelResolver::notSet() : ($labels[$key] ?? $key), 'value' => $value, 'suppressed' => $row['suppressed']];
        }

        $aggregate = $metric->kind()->aggregate();

        if ($rows->truncated && $total !== null && in_array($aggregate, [Aggregate::Count, Aggregate::Sum], true) && array_filter($groups, static fn (array $group): bool => $group['suppressed']) === []) {
            $groups[] = ['key' => null, 'label' => (string) __('gcego-analytics::messages.other'), 'value' => $this->round($metric, $total - $shown), 'suppressed' => false];
        }

        return [$groups, $rows->truncated];
    }

    /**
     * @param  list<string>  $dimensions
     * @param  array<string, 'asc'|'desc'>  $sort
     */
    private function query(Metric $metric, Dataset $dataset, MetricRequest $request, ?DateRange $range, ?TimeInterval $interval = null, array $dimensions = [], array $sort = [], ?int $limit = null): AnalyticsQuery
    {
        $filters = [];

        foreach ($metric->filters() as $target => $value) {
            $filters[] = new AnalyticsFilter($target, is_array($value) ? FilterOperator::In : FilterOperator::Equals, $value, trusted: true);
        }

        foreach ($request->filters as $filter) {
            if ($dataset->field($filter->target) !== null || $dataset->dimension($filter->target) !== null) {
                $filters[] = $filter;
            } elseif ($request->strict) {
                throw AnalyticsQueryException::rejected('filter', sprintf('"%s" is not an available filter.', mb_substr($filter->target, 0, 40)));
            }
        }

        $dateColumn = $metric->dateColumn() ?? $dataset->dateColumn();
        $aggregate = $metric->kind()->aggregate() ?? Aggregate::Count;

        return new AnalyticsQuery(
            dataset: $dataset,
            measures: [new Measure('value', $aggregate, $aggregate === Aggregate::Count ? null : $metric->field())],
            dimensions: $dimensions,
            filters: $filters,
            range: $dateColumn === null ? null : $range,
            dateColumn: $dateColumn,
            interval: $dateColumn === null ? null : $interval,
            sort: $sort,
            limit: $limit,
        );
    }

    /**
     * @param  array<string, int|float|null>  $values
     */
    private function combine(Metric $metric, array $values): int|float|null
    {
        $numerator = $values[$metric->numerator() ?? ''] ?? null;
        $denominator = $values[$metric->denominator() ?? ''] ?? null;
        $ratio = $numerator === null || $denominator === null || (float) $denominator === 0.0 ? null : (float) $numerator / (float) $denominator;

        $result = match ($metric->kind()) {
            MetricKind::Ratio => $ratio,
            MetricKind::Percentage => $ratio === null ? null : $ratio * 100,
            MetricKind::Rate => $ratio === null ? null : $ratio * $metric->per(),
            default => $metric->formula()?->evaluate($values),
        };

        return $this->round($metric, $result);
    }

    private function round(Metric $metric, mixed $value): int|float|null
    {
        $number = self::num($value);

        return is_float($number) ? round($number, $metric->precision()) : $number;
    }

    /**
     * @param  list<string>  $stack
     */
    private function allowed(Metric $metric, AnalyticsContext $context, ?Dashboard $dashboard, array $stack): bool
    {
        if (count($stack) > 20) {
            return false;
        }

        if (! $metric->kind()->isComposite()) {
            return $this->authorizer->metric($metric, $this->datasetOf($metric), $context);
        }

        if (! $this->authorizer->metric($metric, null, $context)) {
            return false;
        }

        foreach ($metric->dependencies() as $dependency) {
            $used = $this->validator->lookupMetric($dependency, $dashboard);

            if ($used === null || ! $this->allowed($used, $context, $dashboard, [...$stack, $metric->nodeName()])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $stack
     */
    private function dependency(string $name, ?Dashboard $dashboard, array $stack): Metric
    {
        if (in_array($name, $stack, true) || count($stack) > 20) {
            throw InvalidDefinitionException::invalid('Metric "'.$name.'"', 'it depends on itself.');
        }

        return $this->validator->lookupMetric($name, $dashboard) ?? throw InvalidDefinitionException::invalid('Metric "'.$name.'"', 'it is not registered.');
    }

    /**
     * The metric and every metric it uses.
     *
     * @return list<Metric>
     */
    private function closure(Metric $metric, ?Dashboard $dashboard): array
    {
        $found = [];
        $queue = [$metric];

        while ($queue !== [] && count($found) < 50) {
            $current = array_shift($queue);
            $found[$current->nodeName()] = $current;

            foreach ($current->dependencies() as $dependency) {
                $next = $this->validator->lookupMetric($dependency, $dashboard);

                if ($next !== null && ! isset($found[$next->nodeName()])) {
                    $queue[] = $next;
                }
            }
        }

        return array_values($found);
    }

    /**
     * @return list<string>
     */
    private function datasetNames(Metric $metric, ?Dashboard $dashboard): array
    {
        $names = [];

        foreach ($this->closure($metric, $dashboard) as $used) {
            $dataset = $this->datasetOf($used);

            if ($dataset !== null) {
                $names[] = $dataset->nodeName();
            }
        }

        return array_values(array_unique($names));
    }

    private function datasetOf(Metric $metric): ?Dataset
    {
        return $this->definitions->datasetOf($metric);
    }

    private static function num(mixed $value): int|float|null
    {
        return is_int($value) || is_float($value) ? $value : null;
    }
}
