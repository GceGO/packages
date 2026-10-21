<?php

declare(strict_types=1);

namespace Gcego\Tables\Filters;

use Gcego\Chroma\Tailwind;
use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Foundation\Makeable;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Data\Contracts\QueryConstraint;
use Gcego\Data\Filters\Filterable;
use Gcego\Data\Support\FieldName;
use Gcego\Tables\Building\FilterBuilder;
use Gcego\Tables\Contracts\TableComponent;
use Gcego\Tables\Exceptions\InvalidTableStateException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The base of every table filter: its presentation and state. Execution
 * belongs to gcego/data: a filter declares an allowlisted Filterable and
 * turns its validated state into QueryInput parameters (or, for custom
 * filters, a trusted constraint).
 *
 * The filter name is what the browser sends; the source field comes from
 * the definition only. State is untrusted and is normalized before use.
 */
abstract class TableFilter extends Component implements TableComponent
{
    use Makeable;

    public const string NamePattern = '/^[a-z][a-z0-9_]{0,63}$/';

    /** The source field to filter (default: the filter name). Relation paths filter through whereHas. */
    public const string Field = 'field';

    /** The initial state (a value valid for this filter). */
    public const string Default = 'default';

    public const string Placeholder = 'placeholder';

    public function filterName(): string
    {
        $name = $this->get(self::Name);

        return is_string($name) ? $name : '';
    }

    public function label(): string
    {
        $label = $this->get(self::Label);

        return is_string($label) ? $label : Str::headline($this->filterName());
    }

    public function field(): string
    {
        $field = $this->get(self::Field);

        return is_string($field) ? $field : $this->filterName();
    }

    /**
     * The Filterable added to the table's source (null for filters that apply constraints).
     */
    abstract public function filterable(): ?Filterable;

    /**
     * Untrusted state to this filter's typed state; null means inactive.
     *
     * @param  class-string<Model>|null  $model  The table's source model (for relationship options).
     *
     * @throws InvalidTableStateException
     */
    abstract public function normalizeState(mixed $value, ?string $model = null): mixed;

    /**
     * gcego/data QueryInput filter parameters for an active (normalized) state.
     *
     * @return array<string, array<string, mixed>>
     */
    public function queryInput(mixed $state): array
    {
        return [];
    }

    /**
     * A trusted constraint for an active state (custom filters).
     */
    public function constraint(mixed $state): ?QueryConstraint
    {
        return null;
    }

    /**
     * Short text describing an active state, for the active-filter indicators.
     *
     * @param  class-string<Model>|null  $model
     */
    abstract public function indicator(mixed $state, ?string $model = null): ?string;

    abstract public function view(): string;

    public function defaultState(): mixed
    {
        try {
            return $this->normalizeState($this->get(self::Default));
        } catch (InvalidTableStateException) {
            return null;
        }
    }

    protected function invalid(string $reason): InvalidTableStateException
    {
        return InvalidTableStateException::invalid('filters.'.$this->filterName(), $reason);
    }

    protected function stringProperty(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) ? $value : null;
    }

    public static function builderClass(): string
    {
        return FilterBuilder::class;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('tables.filters.'.Str::kebab(class_basename(static::class)))
            ->withoutProperties(self::Id, self::Description)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: static fn (mixed $value): bool => is_string($value) && preg_match(self::NamePattern, $value) === 1, ruleDescription: 'a lowercase snake_case name'),
                new PropertyDefinition(self::Field, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && FieldName::isValid($value), ruleDescription: 'a field name or relation path'),
                new PropertyDefinition(self::Default, Type::mixed(), nullable: true),
                new PropertyDefinition(self::Placeholder, Type::string()),
            )
            ->withoutChildren()
            ->withAllowedConfigurations([Tailwind::class, FilterQuery::class])
            ->withConstraint(static function (ComponentInterface $filter): ?string {
                if (! $filter instanceof self) {
                    return null;
                }

                try {
                    $filter->filterable();
                } catch (\Throwable $exception) {
                    return $exception->getMessage();
                }

                return null;
            });
    }

    /**
     * @return list<string>
     */
    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
