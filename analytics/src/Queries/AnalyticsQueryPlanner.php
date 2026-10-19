<?php

declare(strict_types=1);

namespace Gcego\Analytics\Queries;

use Carbon\CarbonImmutable;
use Gcego\Analytics\AnalyticsConfig;
use Gcego\Analytics\Datasets\DatasetSchema;
use Gcego\Analytics\Datasets\Field;
use Gcego\Analytics\Datasets\FieldType;
use Gcego\Analytics\Definitions\AnalyticsNode;
use Gcego\Analytics\Dimensions\Dimension;
use Gcego\Analytics\Events\AnalyticsQueryRejected;
use Gcego\Analytics\Exceptions\AnalyticsAuthorizationException;
use Gcego\Analytics\Exceptions\AnalyticsQueryException;
use Gcego\Analytics\Filters\AnalyticsFilter;
use Gcego\Analytics\Filters\FilterOperator;
use Gcego\Analytics\Metrics\Metric;
use Gcego\Analytics\Security\AnalyticsAuthorizer;
use Gcego\Analytics\Security\AnalyticsContext;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

/**
 * Validates a query against the viewer's dataset schema before anything
 * runs:
 *
 * - the viewer may use the dataset (deny by default);
 * - measures use declared, authorized fields with allowed aggregates;
 * - dimensions are declared and authorized (at most three);
 * - filters target filterable fields or dimensions with operators and
 *   values that fit their type (option dimensions: only their keys);
 * - a date range or time series needs the dataset's date column, stays
 *   within queries.max_range_days and queries.max_chart_points;
 * - sorting uses measure aliases or dimensions only;
 * - rows are bounded (queries.max_rows, max_group_cardinality, max_page_size).
 *
 * Nothing here is SQL: columns come from definitions, values are bound later.
 */
final readonly class AnalyticsQueryPlanner
{
    public const int MaxDimensions = 3;

    public const int MaxListValues = 100;

    public function __construct(private AnalyticsAuthorizer $authorizer, private AnalyticsConfig $config, private Container $container) {}

    public function plan(AnalyticsQuery $query, AnalyticsContext $context, ?Metric $implicitFor = null): QueryPlan
    {
        try {
            return $this->build($query, $context, $implicitFor);
        } catch (AnalyticsQueryException|AnalyticsAuthorizationException $exception) {
            $this->container->make(Dispatcher::class)->dispatch(new AnalyticsQueryRejected($query->dataset->nodeName(), $exception instanceof AnalyticsQueryException ? $exception->reason : 'authorization', $context->tenant, $context->correlation));

            throw $exception;
        }
    }

    private function build(AnalyticsQuery $query, AnalyticsContext $context, ?Metric $implicitFor): QueryPlan
    {
        $dataset = $query->dataset;

        if (! $this->authorizer->dataset($dataset, $context, $implicitFor)) {
            throw AnalyticsAuthorizationException::for('use this dataset');
        }

        $schema = DatasetSchema::for($dataset, $this->authorizer, $context);
        $columns = [];

        if ($query->measures === []) {
            throw AnalyticsQueryException::rejected('measure', 'A query needs at least one measure.');
        }

        $aliases = [];

        foreach ($query->measures as $measure) {
            if (isset($aliases[$measure->alias])) {
                throw AnalyticsQueryException::rejected('measure', sprintf('The measure "%s" is requested twice.', $measure->alias));
            }

            $aliases[$measure->alias] = true;

            if ($measure->field === null) {
                continue;
            }

            $field = $schema->fields[$measure->field] ?? null;

            if ($field === null) {
                throw AnalyticsQueryException::rejected('field', sprintf('"%s" is not an available field.', self::clip($measure->field)));
            }

            if (! $field->allows($measure->aggregate)) {
                throw AnalyticsQueryException::rejected('aggregate', sprintf('%s is not allowed on "%s".', $measure->aggregate->value, $field->nodeName()));
            }

            $columns[$field->nodeName()] = $field->column();
        }

        if (count($query->dimensions) > self::MaxDimensions) {
            throw AnalyticsQueryException::rejected('dimension', sprintf('Group by at most %d dimensions.', self::MaxDimensions));
        }

        $dimensions = [];

        foreach (array_values(array_unique($query->dimensions)) as $name) {
            $dimension = $schema->dimensions[$name] ?? null;

            if ($dimension === null) {
                throw AnalyticsQueryException::rejected('dimension', sprintf('"%s" is not an available dimension.', self::clip($name)));
            }

            $dimensions[] = $dimension;
            $columns[$dimension->nodeName()] = $dimension->column();
        }

        $filters = [];

        foreach ($query->filters as $filter) {
            [$filters[], $column] = $this->filter($filter, $schema);
            $columns[$filter->target] = $column;
        }

        $dateColumn = $query->dateColumn ?? $dataset->dateColumn();

        if ($dateColumn !== null && (! AnalyticsNode::validColumn($dateColumn) || (($field = $dataset->field($dateColumn)) !== null && ! $field->fieldType()->isTemporal()))) {
            throw AnalyticsQueryException::rejected('date_column', 'The date column is not a date field.');
        }

        if (($query->range !== null || $query->interval !== null) && $dateColumn === null) {
            throw AnalyticsQueryException::rejected('date_column', sprintf('Dataset "%s" has no date column for periods.', $dataset->nodeName()));
        }

        if ($query->interval !== null && $query->range === null) {
            throw AnalyticsQueryException::rejected('date_range', 'A time series needs a period.');
        }

        if ($query->range !== null && $query->range->days() > $this->config->maxRangeDays) {
            throw AnalyticsQueryException::rejected('date_range', sprintf('Choose a period of at most %d days.', $this->config->maxRangeDays));
        }

        if ($query->interval !== null && $query->range !== null) {
            $start = $query->range->start;
            $end = $query->range->end();

            if ($query->interval->count($start, $end) > $this->config->maxChartPoints) {
                throw AnalyticsQueryException::rejected('interval', sprintf('That interval makes more than %d points; choose a longer interval or a shorter period.', $this->config->maxChartPoints));
            }
        }

        $sort = [];

        foreach ($query->sort as $key => $direction) {
            if (! isset($aliases[$key]) && ! isset($schema->dimensions[$key]) && $key !== 'bucket') {
                throw AnalyticsQueryException::rejected('sort', sprintf('Cannot sort by "%s".', self::clip($key)));
            }

            if ($key !== 'bucket' && isset($schema->dimensions[$key]) && ! in_array($key, array_map(static fn (Dimension $dimension): string => $dimension->nodeName(), $dimensions), true)) {
                throw AnalyticsQueryException::rejected('sort', sprintf('"%s" is not part of the grouping.', $key));
            }

            $sort[$key] = $direction === 'asc' ? 'asc' : 'desc';
        }

        $grouped = $dimensions !== [] || $query->interval !== null;
        $ceiling = ! $grouped ? 1 : ($query->interval !== null && $dimensions === [] ? $this->config->maxChartPoints : $this->config->maxGroupCardinality);
        $maxRows = $query->rowCeiling ?? $this->config->maxRows;
        $limit = min($query->limit ?? $ceiling, $maxRows);

        if ($query->limit !== null && $query->limit > $maxRows) {
            throw AnalyticsQueryException::rejected('limit', sprintf('At most %d rows.', $maxRows));
        }

        if ($query->offset < 0 || $query->offset + $limit > $maxRows) {
            throw AnalyticsQueryException::rejected('offset', sprintf('Only the first %d rows can be read.', $maxRows));
        }

        return new QueryPlan(
            dataset: $dataset,
            measures: $query->measures,
            dimensions: $dimensions,
            filters: $filters,
            columns: $columns,
            range: $query->range,
            dateColumn: $dateColumn,
            interval: $query->interval,
            sort: $sort,
            limit: max(1, $limit),
            offset: $query->offset,
            withTotal: $query->withTotal,
            minimumGroupSize: $dataset->minimumGroupSize() ?? $this->config->minimumGroupSize,
            timezone: $query->range->timezone ?? $context->timezone,
        );
    }

    /**
     * @return array{0: AnalyticsFilter, 1: string}
     */
    private function filter(AnalyticsFilter $filter, DatasetSchema $schema): array
    {
        $field = $schema->fields[$filter->target] ?? null;
        $dimension = $schema->dimensions[$filter->target] ?? null;

        // Trusted (definition) filters may use any declared field; untrusted ones only filterable, available ones.
        if ($filter->trusted && $field === null && $dimension === null) {
            $field = $schema->dataset->field($filter->target);
            $dimension = $schema->dataset->dimension($filter->target);
        }

        if ($field === null && $dimension === null) {
            throw AnalyticsQueryException::rejected('filter', sprintf('"%s" is not an available filter.', self::clip($filter->target)));
        }

        if (! $filter->trusted && (($field !== null && (! $field->isFilterable() || $field->isSensitive())) || ($dimension !== null && ! $dimension->isFilterable()))) {
            throw AnalyticsQueryException::rejected('filter', sprintf('"%s" cannot be filtered.', $filter->target));
        }

        $operator = $filter->operator;
        $value = $filter->value;

        if ($operator->takesNoValue()) {
            return [new AnalyticsFilter($filter->target, $operator, null, $filter->trusted), $field?->column() ?? $dimension?->column() ?? $filter->target];
        }

        $values = is_array($value) ? $value : [$value];

        if ($operator->takesList() !== is_array($value) || ($operator === FilterOperator::Between && count($values) !== 2) || $values === [] || count($values) > self::MaxListValues) {
            throw AnalyticsQueryException::rejected('filter', sprintf('The value of "%s" does not fit %s.', $filter->target, $operator->value));
        }

        if ($dimension !== null) {
            if (in_array($operator, [FilterOperator::GreaterThan, FilterOperator::GreaterOrEqual, FilterOperator::LessThan, FilterOperator::LessOrEqual, FilterOperator::Between], true)) {
                throw AnalyticsQueryException::rejected('filter', sprintf('"%s" is compared by value only.', $filter->target));
            }

            $options = $dimension->options();
            $normalized = [];

            foreach ($values as $entry) {
                if (! is_scalar($entry) || ($options !== [] && ! array_key_exists((string) $entry, $options))) {
                    throw AnalyticsQueryException::rejected('filter', sprintf('That value is not an option of "%s".', $filter->target));
                }

                $normalized[] = $dimension->dimensionType() === 'boolean' ? (in_array($entry, [true, 1, '1', 'true'], true) ? 1 : 0) : (is_bool($entry) ? (int) $entry : $entry);
            }

            return [new AnalyticsFilter($filter->target, $operator, is_array($value) ? $normalized : $normalized[0], $filter->trusted), $dimension->column()];
        }

        $normalized = array_map(fn (mixed $entry): string|int|float => $this->value($field, $entry), $values);

        return [new AnalyticsFilter($filter->target, $operator, is_array($value) ? $normalized : $normalized[0], $filter->trusted), $field?->column() ?? $filter->target];
    }

    private function value(?Field $field, mixed $value): string|int|float
    {
        $type = $field?->fieldType() ?? FieldType::String;

        return match ($type) {
            FieldType::Number => is_numeric($value) ? (float) $value : throw AnalyticsQueryException::rejected('filter', 'Use a number.'),
            FieldType::Integer => is_numeric($value) && (int) $value == $value ? (int) $value : throw AnalyticsQueryException::rejected('filter', 'Use a whole number.'),
            FieldType::Boolean => in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true) ? (in_array($value, [true, 1, '1', 'true'], true) ? 1 : 0) : throw AnalyticsQueryException::rejected('filter', 'Use true or false.'),
            FieldType::Date, FieldType::DateTime => self::date($value),
            FieldType::String => is_scalar($value) && mb_strlen((string) $value) <= 255 ? (string) $value : throw AnalyticsQueryException::rejected('filter', 'Use a shorter value.'),
        };
    }

    private static function date(mixed $value): string
    {
        try {
            return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $value) === 1 ? CarbonImmutable::parse($value)->format('Y-m-d H:i:s') : throw AnalyticsQueryException::rejected('filter', 'Use a date such as 2026-01-31.');
        } catch (AnalyticsQueryException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw AnalyticsQueryException::rejected('filter', 'Use a date such as 2026-01-31.');
        }
    }

    private static function clip(string $value): string
    {
        return mb_substr(preg_replace('/[^\w\-.]/u', '?', $value) ?? '', 0, 40);
    }
}
