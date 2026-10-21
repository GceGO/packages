<?php

declare(strict_types=1);

namespace Gcego\Analytics\Metrics;

use Gcego\Analytics\Datasets\Dataset;
use Gcego\Analytics\Datasets\Field;
use Gcego\Analytics\Datasets\FieldType;
use Gcego\Analytics\Definitions\AnalyticsNode;
use Gcego\Analytics\Support\Format;
use Gcego\Analytics\TimeSeries\FillStrategy;
use Gcego\Analytics\TimeSeries\TimeInterval;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Properties\Property;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Something to measure:
 *
 *     new Metric('total_students', Student::class)                                       // a count
 *     new Metric(name: 'average_score', dataset: 'assessment-results', aggregate: 'avg', field: 'score', precision: 1)
 *     new Metric(name: 'daily_registrations', model: Student::class, dateColumn: 'created_at', interval: 'day')
 *     new Metric(name: 'pass_rate', aggregate: 'percentage', numerator: 'passed_attempts', denominator: 'attempts')
 *     new Metric(name: 'net_score', aggregate: 'derived', formula: '{points} - {penalties}')
 *
 * A metric reads from a registered Dataset, or directly from a Model (an
 * implicit dataset with only the columns the metric itself names).
 * Filters are trusted equality conditions on declared fields. Ratio,
 * percentage, rate and derived metrics combine other metrics calculated in
 * the same context (period, filters, viewer); a zero denominator yields no
 * value. Registry kind "metric".
 */
final class Metric extends AnalyticsNode
{
    public const string Dataset = 'dataset';

    public const string Model = 'model';

    /** count, sum, avg, min, max, count_distinct, ratio, percentage, rate or derived. */
    public const string Aggregate = 'aggregate';

    public const string Field = 'field';

    /** Trusted equality conditions: ['status' => 'completed', 'level' => ['o', 'a']]. */
    public const string Filters = 'filters';

    public const string DateColumn = 'date_column';

    /** A default time-series interval: hour, day, week, month, quarter or year. */
    public const string Interval = 'interval';

    public const string Format = 'format';

    public const string Unit = 'unit';

    public const string Precision = 'precision';

    public const string Currency = 'currency';

    public const string Numerator = 'numerator';

    public const string Denominator = 'denominator';

    public const string Formula = 'formula';

    /** Rate multiplier (per 100, per 1000...). */
    public const string Per = 'per';

    /** Cache seconds (0: never cached; default: gcego-analytics.cache.ttl). */
    public const string Cache = 'cache';

    /** zero, null or none for empty time-series buckets (default: zero for counts and sums, null otherwise). */
    public const string Fill = 'fill';

    public function kind(): MetricKind
    {
        return MetricKind::tryFrom($this->text(self::Aggregate) ?? '') ?? MetricKind::Count;
    }

    public function datasetName(): ?string
    {
        return $this->text(self::Dataset);
    }

    /**
     * @return class-string<Model>|null
     */
    public function model(): ?string
    {
        $model = $this->get(self::Model);

        return is_string($model) && is_subclass_of($model, Model::class) ? $model : null;
    }

    public function field(): ?string
    {
        return $this->text(self::Field);
    }

    /**
     * @return array<string, scalar|list<scalar>>
     */
    public function filters(): array
    {
        $filters = $this->get(self::Filters);
        $result = [];

        foreach (is_array($filters) ? $filters : [] as $field => $value) {
            if (is_string($field) && (is_scalar($value) || (is_array($value) && array_filter($value, static fn (mixed $entry): bool => ! is_scalar($entry)) === []))) {
                $result[$field] = is_array($value) ? array_values(array_filter($value, is_scalar(...))) : $value;
            }
        }

        return $result;
    }

    public function dateColumn(): ?string
    {
        return $this->text(self::DateColumn);
    }

    public function interval(): ?TimeInterval
    {
        return TimeInterval::tryFrom($this->text(self::Interval) ?? '');
    }

    public function format(): Format
    {
        return Format::tryFrom($this->text(self::Format) ?? '') ?? ($this->kind() === MetricKind::Percentage ? Format::Percentage : Format::Number);
    }

    public function unit(): ?string
    {
        return $this->text(self::Unit);
    }

    public function precision(): int
    {
        return $this->integer(self::Precision) ?? (in_array($this->kind(), [MetricKind::Count, MetricKind::CountDistinct], true) ? 0 : 2);
    }

    public function currency(): ?string
    {
        return $this->text(self::Currency);
    }

    public function numerator(): ?string
    {
        return $this->text(self::Numerator);
    }

    public function denominator(): ?string
    {
        return $this->text(self::Denominator);
    }

    public function formula(): ?Formula
    {
        $formula = $this->text(self::Formula);

        return $formula === null ? null : Formula::parse($formula);
    }

    public function per(): float
    {
        $per = $this->get(self::Per);

        return is_int($per) || is_float($per) ? (float) $per : 1000.0;
    }

    public function cacheSeconds(): ?int
    {
        return $this->integer(self::Cache);
    }

    public function fill(): FillStrategy
    {
        $fill = FillStrategy::tryFrom($this->text(self::Fill) ?? '');

        return $fill ?? ($this->kind()->aggregate()?->zeroWhenEmpty() === false ? FillStrategy::Null : FillStrategy::Zero);
    }

    /**
     * Metrics this one is calculated from.
     *
     * @return list<string>
     */
    public function dependencies(): array
    {
        return match ($this->kind()) {
            MetricKind::Ratio, MetricKind::Percentage, MetricKind::Rate => array_values(array_filter([$this->numerator(), $this->denominator()], is_string(...))),
            MetricKind::Derived => self::safely(fn (): array => $this->formula()?->references() ?? []),
            default => [],
        };
    }

    /**
     * The dataset of a metric declared on a model: only the columns the metric names.
     */
    public function implicitDataset(): ?Dataset
    {
        $model = $this->model();

        if ($model === null) {
            return null;
        }

        $fields = [];
        $field = $this->field();

        if ($field !== null) {
            $fields[$field] = new Field($field, type: FieldType::Number->value);
        }

        if (($date = $this->dateColumn()) !== null) {
            $fields[$date] = new Field($date, type: FieldType::DateTime->value, aggregates: ['count', 'min', 'max']);
        }

        foreach (array_keys($this->filters()) as $filter) {
            $fields[$filter] ??= new Field($filter, type: FieldType::String->value);
        }

        return new Dataset(new Property(Dataset::Name, 'metric-'.$this->nodeName()), new Property(Dataset::Model, $model), ...array_values($fields), ...($this->dateColumn() === null ? [] : [new Property(Dataset::DateColumn, $this->dateColumn())]));
    }

    /**
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];
        $kind = $this->kind();

        if ($kind->isComposite()) {
            if ($this->datasetName() !== null || $this->model() !== null || $this->field() !== null) {
                $problems[] = sprintf('a %s metric is calculated from other metrics; it takes no dataset, model or field.', $kind->value);
            }

            if ($kind === MetricKind::Derived) {
                try {
                    $references = $this->formula()?->references();
                    $problems = [...$problems, ...($references === null ? ['a derived metric needs a Formula.'] : ($references === [] ? ['a derived metric\'s formula must reference at least one {metric}.'] : []))];
                } catch (Throwable $exception) {
                    $problems[] = $exception->getMessage();
                }
            } elseif ($this->numerator() === null || $this->denominator() === null) {
                $problems[] = sprintf('a %s metric needs a Numerator and a Denominator metric.', $kind->value);
            }

            if (in_array($this->nodeName(), $this->dependencies(), true)) {
                $problems[] = 'a metric cannot be calculated from itself.';
            }

            return $problems;
        }

        if (($this->datasetName() === null) === ($this->model() === null)) {
            $problems[] = 'a metric needs exactly one of Dataset (a registered dataset) or Model.';
        }

        if ($kind->aggregate()?->needsField() === true && $this->field() === null) {
            $problems[] = sprintf('a %s metric needs a Field.', $kind->value);
        }

        if ($this->interval() !== null && $this->dateColumn() === null && $this->model() !== null) {
            $problems[] = 'a time-series metric needs a DateColumn.';
        }

        foreach (array_keys($this->filters()) as $filter) {
            if (! self::validColumn($filter)) {
                $problems[] = sprintf('the filter "%s" is not a column name.', mb_substr($filter, 0, 64));
            }
        }

        return $problems;
    }

    protected static function namedArguments(): array
    {
        return [...parent::namedArguments(), 'dataset' => self::Dataset, 'model' => self::Model, 'aggregate' => self::Aggregate, 'field' => self::Field, 'filters' => self::Filters, 'dateColumn' => self::DateColumn, 'interval' => self::Interval, 'format' => self::Format, 'unit' => self::Unit, 'precision' => self::Precision, 'currency' => self::Currency, 'numerator' => self::Numerator, 'denominator' => self::Denominator, 'formula' => self::Formula, 'per' => self::Per, 'cache' => self::Cache, 'fill' => self::Fill];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $metricName = static fn (string $key): PropertyDefinition => new PropertyDefinition($key, Type::string(), rule: self::validName(...), ruleDescription: 'a metric name');

        return parent::defineSchema()
            ->withType('analytics.metric')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validName(...), ruleDescription: 'a lowercase name such as "total_students"'),
                new PropertyDefinition(self::Dataset, Type::string(), rule: self::validName(...), ruleDescription: 'a dataset name'),
                new PropertyDefinition(self::Model, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && is_subclass_of($value, Model::class), ruleDescription: 'an Eloquent model class'),
                new PropertyDefinition(self::Aggregate, Type::string(), default: 'count', allowedValues: MetricKind::values()),
                new PropertyDefinition(self::Field, Type::string(), rule: self::validColumn(...), ruleDescription: 'a field of the dataset'),
                new PropertyDefinition(self::Filters, Type::array(), rule: static fn (mixed $value): bool => is_array($value) && count($value) <= 20, ruleDescription: 'at most 20 field => value conditions'),
                new PropertyDefinition(self::DateColumn, Type::string(), rule: self::validColumn(...), ruleDescription: 'a column of the dataset'),
                new PropertyDefinition(self::Interval, Type::string(), allowedValues: TimeInterval::values()),
                new PropertyDefinition(self::Format, Type::string(), allowedValues: Format::values()),
                new PropertyDefinition(self::Unit, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && mb_strlen($value) <= 20, ruleDescription: 'at most 20 characters'),
                new PropertyDefinition(self::Precision, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 0 && $value <= 6, ruleDescription: 'between 0 and 6'),
                new PropertyDefinition(self::Currency, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[A-Z]{3}$/', $value) === 1, ruleDescription: 'an ISO 4217 code'),
                $metricName(self::Numerator), $metricName(self::Denominator),
                new PropertyDefinition(self::Formula, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && $value !== '', ruleDescription: 'a formula'),
                new PropertyDefinition(self::Per, Type::mixed(), rule: static fn (mixed $value): bool => (is_int($value) || is_float($value)) && $value > 0, ruleDescription: 'a positive number'),
                new PropertyDefinition(self::Cache, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 0 && $value <= 86400, ruleDescription: 'seconds between 0 and 86400'),
                new PropertyDefinition(self::Fill, Type::string(), allowedValues: ['zero', 'null', 'none']),
            )
            ->withoutChildren()
            ->withConstraint(static fn (ComponentInterface $metric): array => $metric instanceof self ? $metric->problems() : []);
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Model];
    }

    /**
     * @param  \Closure(): list<string>  $callback
     * @return list<string>
     */
    private static function safely(\Closure $callback): array
    {
        try {
            return $callback();
        } catch (Throwable) {
            return [];
        }
    }
}
