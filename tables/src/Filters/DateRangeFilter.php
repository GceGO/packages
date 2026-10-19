<?php

declare(strict_types=1);

namespace Gcego\Tables\Filters;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\Property;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Data\Filters\Filterable;
use Gcego\Data\Filters\FilterOperator;
use Gcego\Data\Filters\ValueType;

/**
 * A from/to date range (either end optional, inclusive):
 *
 *     new DateRangeFilter('starts_on', 'Starts between')
 *     new DateRangeFilter('created_at', 'Created', new Property(DateRangeFilter::Time, true))
 *
 * State: ['from' => 'YYYY-MM-DD', 'to' => 'YYYY-MM-DD']; from after to is refused.
 */
class DateRangeFilter extends TableFilter
{
    public const string Time = 'time';

    public function view(): string
    {
        return 'gcego-tables::filters.date-range';
    }

    public function filterable(): Filterable
    {
        return new Filterable($this->filterName(), [FilterOperator::GreaterThanOrEqual, FilterOperator::LessThanOrEqual], ValueType::DateTime, $this->field());
    }

    public function normalizeState(mixed $value, ?string $model = null): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (! is_array($value) || array_diff(array_keys($value), ['from', 'to']) !== []) {
            throw $this->invalid('must be a range with "from" and "to".');
        }

        $from = DateFilter::date($value['from'] ?? null, fn (string $reason) => $this->invalid($reason));
        $to = DateFilter::date($value['to'] ?? null, fn (string $reason) => $this->invalid($reason));

        if ($from === null && $to === null) {
            return null;
        }

        if ($from !== null && $to !== null && $from > $to) {
            throw $this->invalid('starts after it ends.');
        }

        return ['from' => $from, 'to' => $to];
    }

    public function queryInput(mixed $state): array
    {
        if (! is_array($state)) {
            return [];
        }

        $day = $this->day();
        $parameters = [];

        if (is_string($state['from'] ?? null)) {
            $parameters['gte'] = $day->bounds($state['from'])[0];
        }

        if (is_string($state['to'] ?? null)) {
            $parameters['lte'] = $day->bounds($state['to'])[1];
        }

        return $parameters === [] ? [] : [$this->filterName() => $parameters];
    }

    public function indicator(mixed $state, ?string $model = null): ?string
    {
        if (! is_array($state)) {
            return null;
        }

        $from = is_string($state['from'] ?? null) ? $state['from'] : null;
        $to = is_string($state['to'] ?? null) ? $state['to'] : null;
        $text = match (true) {
            $from !== null && $to !== null => __('gcego-tables::messages.range_between', ['from' => DateFilter::display($from), 'to' => DateFilter::display($to)]),
            $from !== null => __('gcego-tables::messages.date_on_or_after', ['date' => DateFilter::display($from)]),
            default => __('gcego-tables::messages.date_on_or_before', ['date' => DateFilter::display((string) $to)]),
        };

        return (string) __($this->label()).': '.(string) $text;
    }

    private function day(): DateFilter
    {
        return new DateFilter($this->filterName(), new Property(DateFilter::Time, $this->get(self::Time) === true));
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(new PropertyDefinition(self::Time, Type::bool(), default: false));
    }
}
