<?php

declare(strict_types=1);

namespace Gcego\Tables\Filters;

use Carbon\CarbonImmutable;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Data\Filters\Filterable;
use Gcego\Data\Filters\FilterOperator;
use Gcego\Data\Filters\ValueType;
use Gcego\Tables\TablesConfig;
use Throwable;

/**
 * One calendar date:
 *
 *     new DateFilter('starts_on', 'Starts on')                                                      // a DATE column
 *     new DateFilter('created_at', 'Created on', new Property(DateFilter::Time, true))              // a DATETIME column: the whole day
 *     new DateFilter('due_on', 'Due before', new Property(DateFilter::Operator, 'before'))
 *
 * With Time, days are taken in the display zone (gcego-tables.dates.timezone)
 * and converted to app.timezone bounds, so "on 1 March" covers that whole day.
 */
class DateFilter extends TableFilter
{
    /** on | before | after | on_or_before | on_or_after */
    public const string Operator = 'operator';

    /** The field stores date-times; compare whole days. */
    public const string Time = 'time';

    public const array Operators = ['on', 'before', 'after', 'on_or_before', 'on_or_after'];

    public function view(): string
    {
        return 'gcego-tables::filters.date';
    }

    public function filterable(): Filterable
    {
        return new Filterable($this->filterName(), [FilterOperator::GreaterThanOrEqual, FilterOperator::LessThanOrEqual, FilterOperator::GreaterThan, FilterOperator::LessThan], ValueType::DateTime, $this->field());
    }

    public function normalizeState(mixed $value, ?string $model = null): mixed
    {
        return self::date($value, fn (string $reason) => $this->invalid($reason));
    }

    public function queryInput(mixed $state): array
    {
        if (! is_string($state)) {
            return [];
        }

        [$start, $end] = $this->bounds($state);

        return [$this->filterName() => match ($this->operator()) {
            'before' => ['lt' => $start],
            'after' => ['gt' => $end],
            'on_or_before' => ['lte' => $end],
            'on_or_after' => ['gte' => $start],
            default => ['gte' => $start, 'lte' => $end],
        }];
    }

    public function indicator(mixed $state, ?string $model = null): ?string
    {
        return is_string($state) ? (string) __($this->label()).': '.(string) __('gcego-tables::messages.date_'.$this->operator(), ['date' => self::display($state)]) : null;
    }

    /**
     * A "YYYY-MM-DD" day for people ("18 Oct 2026", in the app locale); unparsable values are returned as they are.
     */
    public static function display(string $day): string
    {
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $day);
        } catch (Throwable) {
            return $day;
        }

        $localized = $date instanceof CarbonImmutable ? $date->locale(app()->getLocale()) : null;

        return $localized instanceof CarbonImmutable ? $localized->translatedFormat('j M Y') : $day;
    }

    public function operator(): string
    {
        $operator = $this->stringProperty(self::Operator);

        return $operator !== null && in_array($operator, self::Operators, true) ? $operator : 'on';
    }

    public function hasTime(): bool
    {
        return $this->get(self::Time) === true;
    }

    /**
     * The first and last instant of a day. DATE fields compare whole days
     * without time-zone shifts (and work whether the driver stores "Y-m-d"
     * or a date cast's "Y-m-d 00:00:00").
     *
     * @return array{0: string, 1: string}
     */
    public function bounds(string $day): array
    {
        if (! $this->hasTime()) {
            return [$day.' 00:00:00', $day.' 23:59:59'];
        }

        $config = app(TablesConfig::class);
        $app = config('app.timezone', 'UTC');
        $storage = is_string($app) ? $app : 'UTC';
        $display = $config->timezone ?? $storage;
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $day, $display);

        if (! $start instanceof CarbonImmutable) {
            return [$day.' 00:00:00', $day.' 23:59:59'];
        }

        return [
            $start->setTimezone($storage)->format('Y-m-d H:i:s'),
            $start->endOfDay()->setTimezone($storage)->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * A Y-m-d date string, or null when empty.
     *
     * @param  \Closure(string): Throwable  $invalid
     */
    public static function date(mixed $value, \Closure $invalid): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw $invalid('must be a date (YYYY-MM-DD).');
        }

        [$year, $month, $day] = array_map(intval(...), explode('-', $value));

        if (! checkdate($month, $day, $year)) {
            throw $invalid('must be a valid date.');
        }

        return $value;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Operator, Type::string(), default: 'on', allowedValues: self::Operators),
            new PropertyDefinition(self::Time, Type::bool(), default: false),
        );
    }
}
