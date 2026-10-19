<?php

declare(strict_types=1);

namespace Gcego\Widgets\Data;

use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Data\Aggregation\AggregateFunction;
use Gcego\Data\Aggregation\Aggregation;
use Gcego\Data\Aggregation\TimeBucket;
use Gcego\Data\Filters\Filter;
use Gcego\Widgets\Exceptions\InvalidWidgetException;
use Illuminate\Database\Eloquent\Model;

/**
 * A database aggregate as a widget's data, executed by gcego/data
 * (DataManager::aggregate) as the current user: the model's viewAny policy
 * (or the blueprint Source's requirements), tenant scopes and mandatory
 * constraints all apply; nothing is loaded into PHP.
 *
 *     new Aggregate(Student::class)                                                    // count
 *     new Aggregate(Payment::class, 'sum', 'amount', date: 'paid_at', compare: true)   // in the selected period, with a trend
 *     new Aggregate(Student::class, groupBy: 'level')                                  // a series for charts
 *     new Aggregate(Enrolment::class, date: 'created_at', bucket: 'month')             // a time series, zero-filled
 *     new Aggregate('blueprint:students', filters: [new Filter('status', 'active')])   // a registered blueprint's Source
 */
final readonly class Aggregate implements ConfigurationNode
{
    public AggregateFunction $function;

    public ?TimeBucket $bucket;

    /**
     * @param  class-string<Model>|string  $source  A model class, or a blueprint identity ("blueprint:students").
     * @param  list<Filter>  $filters  Trusted, fixed conditions.
     */
    public function __construct(
        public string $source,
        AggregateFunction|string $function = AggregateFunction::Count,
        public ?string $column = null,
        public ?string $groupBy = null,
        public ?string $date = null,
        TimeBucket|string|null $bucket = null,
        public bool $compare = false,
        public int $limit = 100,
        public array $filters = [],
        public ?string $label = null,
    ) {
        $this->function = $function instanceof AggregateFunction ? $function : (AggregateFunction::tryFrom($function) ?? throw InvalidWidgetException::invalid('Aggregate', sprintf('"%s" is not count, sum, avg, min or max.', mb_substr($function, 0, 20))));
        $this->bucket = $bucket instanceof TimeBucket || $bucket === null ? $bucket : (TimeBucket::tryFrom($bucket) ?? throw InvalidWidgetException::invalid('Aggregate', 'bucket must be day, week, month or year.'));

        if (! str_starts_with($source, 'blueprint:') && ! is_subclass_of($source, Model::class)) {
            throw InvalidWidgetException::invalid('Aggregate', 'the source must be an Eloquent model class or a blueprint identity.');
        }

        if ($compare && $date === null) {
            throw InvalidWidgetException::invalid('Aggregate', 'compare needs a date column.');
        }

        foreach ($filters as $filter) {
            if (! $filter instanceof Filter) {
                throw InvalidWidgetException::invalid('Aggregate', 'filters must be gcego/data Filter nodes.');
            }
        }

        $this->aggregation();
    }

    public function isGrouped(): bool
    {
        return $this->groupBy !== null || $this->bucket !== null;
    }

    /**
     * The gcego/data aggregation, over a period when the aggregate has a date column.
     */
    public function aggregation(?Period $period = null): Aggregation
    {
        try {
            return new Aggregation($this->function, $this->column, $this->groupBy, $this->date, $this->bucket, $this->date === null ? null : $period?->from, $this->date === null ? null : $period?->to, $this->limit);
        } catch (\Throwable $exception) {
            throw InvalidWidgetException::invalid('Aggregate', $exception->getMessage());
        }
    }

    /**
     * @return array<string, scalar|null>
     */
    public function normalize(): array
    {
        return ['source' => $this->source, 'function' => $this->function->value, 'column' => $this->column, 'group_by' => $this->groupBy, 'date' => $this->date, 'bucket' => $this->bucket?->value, 'compare' => $this->compare, 'limit' => $this->limit];
    }
}
