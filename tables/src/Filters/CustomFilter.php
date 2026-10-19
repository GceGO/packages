<?php

declare(strict_types=1);

namespace Gcego\Tables\Filters;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Data\Contracts\QueryConstraint;
use Gcego\Data\Filters\Filterable;
use Gcego\Data\Query\Query;
use Gcego\Tables\Exceptions\InvalidTableException;
use Illuminate\Database\Eloquent\Model;

/**
 * A filter with its own trusted query, for conditions a Filterable cannot express:
 *
 *     new CustomFilter('overdue', 'Overdue', new FilterQuery(fn (Builder $query, bool $on) => $on ? $query->where('due_on', '<', today()) : $query))
 *     new CustomFilter('band', 'Score band', new Property(CustomFilter::Input, 'select'), new Property(CustomFilter::Options, ['low' => 'Low', 'high' => 'High']),
 *         new FilterQuery(fn (Builder $query, string $band) => $band === 'high' ? $query->where('score', '>=', 70) : $query->where('score', '<', 70)))
 *
 * The control is a toggle (Input "boolean", default), a select, a date or a
 * short text field. The value is validated for that control before the
 * closure sees it; the closure runs as a mandatory constraint of this
 * request, alongside every scope and policy of the source.
 */
class CustomFilter extends TableFilter
{
    /** boolean | select | date | text */
    public const string Input = 'input';

    public const string Options = 'options';

    public const array Inputs = ['boolean', 'select', 'date', 'text'];

    public function view(): string
    {
        return match ($this->input()) {
            'select' => 'gcego-tables::filters.select',
            'date' => 'gcego-tables::filters.date',
            'text' => 'gcego-tables::filters.text',
            default => 'gcego-tables::filters.boolean',
        };
    }

    public function input(): string
    {
        $input = $this->stringProperty(self::Input);

        return $input !== null && in_array($input, self::Inputs, true) ? $input : 'boolean';
    }

    public function isMultiple(): bool
    {
        return false;
    }

    /**
     * @param  class-string<Model>|null  $model  Unused: custom options are static.
     * @return array<string, string>
     */
    public function options(?string $model = null): array
    {
        $options = $this->get(self::Options);

        return is_array($options) ? array_map(static fn (mixed $label): string => is_scalar($label) ? (string) $label : '', array_combine(array_map(strval(...), array_keys($options)), array_values($options))) : [];
    }

    public function filterable(): ?Filterable
    {
        if (! $this->configurations()->has(FilterQuery::class)) {
            throw InvalidTableException::invalid(sprintf('CustomFilter "%s"', $this->filterName()), 'it needs a FilterQuery node.');
        }

        return null;
    }

    public function normalizeState(mixed $value, ?string $model = null): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($this->input()) {
            'boolean' => (new BooleanFilter($this->filterName()))->normalizeState($value) ?: null,
            'date' => DateFilter::date($value, fn (string $reason) => $this->invalid($reason)),
            'select' => is_string($value) && array_key_exists($value, $this->options()) ? $value : throw $this->invalid('is not one of the options.'),
            default => is_string($value) && mb_strlen($value) <= 100 && preg_match('/[\x00-\x1F\x7F]/', $value) !== 1 ? (trim($value) === '' ? null : trim($value)) : throw $this->invalid('must be a short text.'),
        };
    }

    public function constraint(mixed $state): ?QueryConstraint
    {
        $node = $this->configurations()->first(FilterQuery::class);

        if (! $node instanceof FilterQuery || $state === null) {
            return null;
        }

        $closure = $node->constraint;

        return new Query(static fn (mixed $query): mixed => $closure($query, $state) ?? $query);
    }

    public function indicator(mixed $state, ?string $model = null): ?string
    {
        return match (true) {
            $state === true => (string) __($this->label()),
            is_string($state) => (string) __($this->label()).': '.(string) __($this->options()[$state] ?? $state),
            default => null,
        };
    }

    public function stateLabel(bool $state): string
    {
        return (string) __($state ? 'gcego-tables::messages.yes' : 'gcego-tables::messages.no');
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Input, Type::string(), default: 'boolean', allowedValues: self::Inputs),
            new PropertyDefinition(self::Options, Type::array(), default: []),
        );
    }
}
