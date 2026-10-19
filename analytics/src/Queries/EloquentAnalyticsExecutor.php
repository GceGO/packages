<?php

declare(strict_types=1);

namespace Gcego\Analytics\Queries;

use Carbon\CarbonImmutable;
use Gcego\Analytics\AnalyticsConfig;
use Gcego\Analytics\Contracts\AnalyticsProvider;
use Gcego\Analytics\Contracts\AnalyticsScope;
use Gcego\Analytics\Exceptions\AnalyticsAuthorizationException;
use Gcego\Analytics\Exceptions\AnalyticsQueryException;
use Gcego\Analytics\Filters\FilterOperator;
use Gcego\Analytics\Measures\Aggregate;
use Gcego\Analytics\Security\AnalyticsContext;
use Gcego\Analytics\Security\ContextScope;
use Gcego\Analytics\Support\Keys;
use Gcego\Analytics\TimeSeries\BucketExpression;
use Gcego\Data\DataManager;
use Gcego\Data\Query\QueryContext;
use Gcego\Data\Source;
use Gcego\Security\Exceptions\SecurityException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\QueryException;

/**
 * Runs a QueryPlan where the data lives, in this order:
 *
 * 1. gcego/data's scoped builder for the dataset's model, as the viewer in
 *    the context's tenant: the model's policy (viewAny), global scopes,
 *    soft deletes and mandatory scopes such as tenancy;
 * 2. the dataset's AnalyticsScope (record-level access);
 * 3. filters and the period, all as bindings;
 * 4. one aggregate query: SELECT dimensions, bucket, aggregates, COUNT(*)
 *    GROUP BY ... ORDER BY ... LIMIT (limit + 1 to detect truncation);
 *    a total for pagination counts the grouped rows in a sub-query.
 *
 * Rows are never loaded into PHP to aggregate. Groups smaller than the
 * minimum group size have their values suppressed.
 */
final readonly class EloquentAnalyticsExecutor
{
    public function __construct(private DataManager $data, private ContextScope $scope, private Container $container, private AnalyticsConfig $config) {}

    public function execute(QueryPlan $plan, AnalyticsContext $context): AnalyticsRows
    {
        return $this->scope->run($context, fn (): AnalyticsRows => $this->run($plan, $context));
    }

    private function run(QueryPlan $plan, AnalyticsContext $context): AnalyticsRows
    {
        $started = hrtime(true);
        $provider = $plan->dataset->provider();

        if ($provider !== null) {
            $instance = $this->container->make($provider);
            $rows = $instance instanceof AnalyticsProvider ? $instance->execute($plan, $context) : new AnalyticsRows([]);

            return new AnalyticsRows($this->suppress(array_slice($rows->rows, 0, $plan->limit), $plan), $rows->truncated || count($rows->rows) > $plan->limit, $rows->total, self::elapsed($started));
        }

        $model = $plan->dataset->model() ?? throw AnalyticsQueryException::rejected('dataset', 'The dataset has no source.');

        try {
            $query = $this->data->scopedQuery(new Source($model), [], QueryContext::for($context->user));
        } catch (AuthorizationException|SecurityException) {
            // gcego/data denies the rows (the model's policy, or no policy at all): deny the analytics too.
            throw AnalyticsAuthorizationException::for('see this data');
        }

        return $this->aggregateOn($query, $plan, $context, $started);
    }

    /**
     * Steps 2–4 on a builder that already holds only the rows the viewer may
     * see (trusted AnalyticsProviders scope their own query, then call this).
     *
     * @param  EloquentBuilder<Model>|QueryBuilder  $query
     */
    public function aggregateOn(EloquentBuilder|QueryBuilder $query, QueryPlan $plan, AnalyticsContext $context, int|float|null $started = null): AnalyticsRows
    {
        $started ??= hrtime(true);
        $qualify = static fn (string $column): string => $query instanceof EloquentBuilder ? $query->qualifyColumn($column) : $column;

        $scope = $plan->dataset->scope();

        if ($scope !== null) {
            $instance = $this->container->make($scope);

            if ($instance instanceof AnalyticsScope) {
                // Its own where group: a scope can only narrow the rows.
                $query->where(static fn (EloquentBuilder|QueryBuilder $group) => $instance->apply($group, $context));
            }
        }

        foreach ($plan->filters as $filter) {
            $column = $qualify($plan->column($filter->target));
            $value = $filter->value;

            match ($filter->operator) {
                FilterOperator::In => $query->whereIn($column, is_array($value) ? $value : [$value]),
                FilterOperator::NotIn => $query->whereNotIn($column, is_array($value) ? $value : [$value]),
                FilterOperator::Between => $query->whereBetween($column, is_array($value) ? array_values($value) : [$value, $value]),
                FilterOperator::IsNull => $query->whereNull($column),
                FilterOperator::NotNull => $query->whereNotNull($column),
                default => $query->where($column, $filter->operator->sql(), is_array($value) ? null : $value),
            };
        }

        $databaseTimezone = self::databaseTimezone();

        if ($plan->range !== null && $plan->dateColumn !== null) {
            [$from, $to] = $plan->range->bindings($databaseTimezone);
            $query->where($qualify($plan->dateColumn), '>=', $from)->where($qualify($plan->dateColumn), '<', $to);
        }

        $base = $query instanceof EloquentBuilder ? $query->toBase() : $query;
        $grammar = $base->getGrammar();
        $connection = $base->getConnection();
        $driver = $connection instanceof Connection ? $connection->getDriverName() : 'sqlite';
        $selects = [];
        $groups = [];

        foreach ($plan->dimensions as $index => $dimension) {
            $expression = $grammar->wrap($qualify($dimension->column()));
            $selects[] = $expression.' as gcego_d'.$index;
            $groups[] = $expression;
        }

        if ($plan->interval !== null && $plan->dateColumn !== null && $plan->range !== null) {
            $offset = self::offset($plan->range->start, $databaseTimezone, $plan->timezone);
            $bucket = BucketExpression::sql($driver, $grammar->wrap($qualify($plan->dateColumn)), $plan->interval, $offset, $databaseTimezone, $plan->timezone);
            $selects[] = $bucket.' as gcego_t';
            $groups[] = $bucket;
        }

        foreach ($plan->measures as $measure) {
            $column = $measure->field === null ? '*' : $grammar->wrap($qualify($plan->column($measure->field)));
            $selects[] = ($measure->aggregate === Aggregate::Count && $measure->field === null ? 'COUNT(*)' : $measure->aggregate->sql($column)).' as '.$grammar->wrap('gcego_m_'.$measure->alias);
        }

        $selects[] = 'COUNT(*) as gcego_n';

        // Identifiers only (validated plan columns wrapped by the grammar, fixed aggregate and bucket SQL); every value is bound.
        $base->selectRaw(implode(', ', $selects)); // @phpstan-ignore argument.type

        if ($groups !== []) {
            $base->groupByRaw(implode(', ', $groups)); // @phpstan-ignore argument.type
        }

        $total = null;

        if ($plan->withTotal && $groups !== []) {
            $total = Keys::int($base->newQuery()->fromSub(clone $base, 'gcego_groups')->count());
        }

        if ($plan->isGrouped()) {
            foreach ($plan->sort as $key => $direction) {
                $alias = $key === 'bucket' ? 'gcego_t' : (($index = self::dimensionIndex($plan, $key)) !== null ? 'gcego_d'.$index : 'gcego_m_'.$key);
                $base->orderBy($alias, $direction);
            }

            // Deterministic order whatever the sort: then by every group key.
            if ($plan->interval !== null && ! isset($plan->sort['bucket'])) {
                $base->orderBy('gcego_t');
            }

            foreach ($plan->dimensions as $index => $dimension) {
                if (! isset($plan->sort[$dimension->nodeName()])) {
                    $base->orderBy('gcego_d'.$index);
                }
            }

            $base->limit($plan->limit + 1)->offset($plan->offset);
        }

        $records = $this->withTimeout($connection, $driver, static fn () => $base->get());
        $rows = [];

        foreach ($records as $record) {
            $data = (array) $record;
            $dimensions = [];

            foreach ($plan->dimensions as $index => $dimension) {
                $key = $data['gcego_d'.$index] ?? null;
                $dimensions[$dimension->nodeName()] = $key === null ? null : (is_bool($key) ? ($key ? '1' : '0') : Keys::string($key));
            }

            $values = [];

            foreach ($plan->measures as $measure) {
                $value = Keys::number($data['gcego_m_'.$measure->alias] ?? null);
                $values[$measure->alias] = $value ?? ($measure->aggregate->zeroWhenEmpty() ? 0 : null);
            }

            $rows[] = ['dimensions' => $dimensions, 'bucket' => isset($data['gcego_t']) ? Keys::string($data['gcego_t']) : null, 'values' => $values, 'count' => Keys::int($data['gcego_n'] ?? 0), 'suppressed' => false];
        }

        $truncated = $plan->isGrouped() && count($rows) > $plan->limit;

        return new AnalyticsRows($this->suppress(array_slice($rows, 0, $plan->limit), $plan), $truncated, $total, self::elapsed($started));
    }

    /**
     * @param  list<array{dimensions: array<string, string|null>, bucket: string|null, values: array<string, int|float|null>, count: int|null, suppressed: bool}>  $rows
     * @return list<array{dimensions: array<string, string|null>, bucket: string|null, values: array<string, int|float|null>, count: int|null, suppressed: bool}>
     */
    private function suppress(array $rows, QueryPlan $plan): array
    {
        if ($plan->minimumGroupSize <= 1) {
            return $rows;
        }

        foreach ($rows as $index => $row) {
            $count = $row['count'] ?? 0;

            if ($count > 0 && $count < $plan->minimumGroupSize) {
                $rows[$index] = [...$row, 'values' => array_map(static fn (): null => null, $row['values']), 'count' => null, 'suppressed' => true];
            }
        }

        return $rows;
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function withTimeout(mixed $connection, string $driver, \Closure $callback): mixed
    {
        $timeout = $this->config->timeoutMs;

        if ($timeout === null || ! $connection instanceof Connection || ! in_array($driver, ['mysql', 'pgsql'], true)) {
            return $callback();
        }

        $connection->statement($driver === 'mysql' ? 'SET SESSION max_execution_time = '.$timeout : 'SET statement_timeout = '.$timeout);

        try {
            return $callback();
        } catch (QueryException $exception) {
            throw AnalyticsQueryException::rejected('timeout', 'The query took too long; narrow the period or the filters.');
        } finally {
            $connection->statement($driver === 'mysql' ? 'SET SESSION max_execution_time = 0' : 'SET statement_timeout = 0');
        }
    }

    private static function dimensionIndex(QueryPlan $plan, string $name): ?int
    {
        foreach ($plan->dimensions as $index => $dimension) {
            if ($dimension->nodeName() === $name) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Seconds to add to database times to read them in the report's time zone, at the start of the period.
     */
    private static function offset(CarbonImmutable $moment, string $databaseTimezone, string $timezone): int
    {
        return $moment->setTimezone($timezone)->getOffset() - $moment->setTimezone($databaseTimezone)->getOffset();
    }

    private static function databaseTimezone(): string
    {
        $timezone = config('app.timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
    }

    private static function elapsed(int|float $started): float
    {
        return round((hrtime(true) - $started) / 1e6, 3);
    }
}
