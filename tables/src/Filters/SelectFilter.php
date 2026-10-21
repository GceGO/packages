<?php

declare(strict_types=1);

namespace Gcego\Tables\Filters;

use BackedEnum;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Data\Filters\Filterable;
use Gcego\Data\Filters\FilterOperator;
use Gcego\Data\Filters\ValueType;
use Gcego\Data\Support\Callback;
use Gcego\Tables\Exceptions\InvalidTableException;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * One or several values from a fixed list:
 *
 *     new SelectFilter('status', 'Status', new Property(SelectFilter::Options, ['draft' => 'Draft', 'published' => 'Published']))
 *     new SelectFilter('kind', 'Kind', new Property(SelectFilter::Options, Kind::class), new Property(SelectFilter::Multiple, true))
 *     new SelectFilter('category', 'Category', new Property(SelectFilter::Relationship, 'category'), new Property(SelectFilter::Field, 'category_id'))
 *     new SelectFilter('level', 'Level', new Property(SelectFilter::Options, new Callback(fn () => Level::query()->pluck('name', 'id')->all())))
 *
 * Prefer Relationship for records: its options are read as the current user
 * with tenant scopes and policies. A Callback is trusted application code;
 * it runs without those scopes, so it must apply them itself.
 *
 * Options are a value => label map, a backed enum class, or a trusted
 * Callback returning a map (called when rendering and validating, never
 * with request data; it may query, so prefer static options for large
 * tables). Submitted values must be options.
 */
class SelectFilter extends TableFilter
{
    public const string Options = 'options';

    public const string Multiple = 'multiple';

    /** A BelongsTo relation of the table's source model: options are its related records, read through gcego/data (scoped, authorized). */
    public const string Relationship = 'relationship';

    /** The related column shown as the option label (default "name"). */
    public const string Title = 'title';

    public function view(): string
    {
        return 'gcego-tables::filters.select';
    }

    public function isMultiple(): bool
    {
        return $this->get(self::Multiple) === true;
    }

    /**
     * @param  class-string<Model>|null  $model  The table's source model (needed for Relationship).
     * @return array<string, string> value => label
     */
    public function options(?string $model = null): array
    {
        $relationship = $this->stringProperty(self::Relationship);

        if ($relationship !== null) {
            return $model === null ? [] : app(RelationshipOptions::class)->for($model, $relationship, $this->stringProperty(self::Title) ?? 'name');
        }

        $source = $this->get(self::Options);

        if ($source instanceof Callback) {
            $source = $source();
        }

        if (is_string($source) && enum_exists($source) && is_subclass_of($source, BackedEnum::class)) {
            $options = [];

            foreach ($source::cases() as $case) {
                $options[(string) $case->value] = method_exists($case, 'label') && is_scalar($label = $case->label()) ? (string) $label : $case->name;
            }

            return $options;
        }

        if (! is_array($source)) {
            throw InvalidTableException::invalid(sprintf('SelectFilter "%s"', $this->filterName()), 'Options must be an array, a backed enum class or a closure returning an array.');
        }

        $options = [];

        foreach ($source as $value => $label) {
            $options[(string) $value] = match (true) {
                is_scalar($label) => (string) $label,
                $label instanceof UnitEnum => $label->name,
                default => (string) $value,
            };
        }

        return $options;
    }

    public function filterable(): Filterable
    {
        return new Filterable($this->filterName(), [FilterOperator::Equals, FilterOperator::In], $this->valueType(), $this->field());
    }

    public function normalizeState(mixed $value, ?string $model = null): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $values = is_array($value) ? $value : [$value];

        if (! array_is_list($values) || count($values) > 100 || (! $this->isMultiple() && count($values) > 1)) {
            throw $this->invalid('has too many values.');
        }

        $options = $this->options($model);
        $normalized = [];

        foreach ($values as $item) {
            if (! is_string($item) && ! is_int($item)) {
                throw $this->invalid('must contain option values.');
            }

            if (! array_key_exists((string) $item, $options)) {
                throw $this->invalid('is not one of the options.');
            }

            $normalized[] = (string) $item;
        }

        $normalized = array_values(array_unique($normalized));

        return $this->isMultiple() ? $normalized : $normalized[0];
    }

    public function queryInput(mixed $state): array
    {
        $values = array_map(fn (string $value): string|int => $this->valueType() === ValueType::Integer ? (int) $value : $value, is_array($state) ? array_values(array_filter($state, is_string(...))) : (is_string($state) ? [$state] : []));

        return $values === [] ? [] : [$this->filterName() => count($values) === 1 ? ['eq' => $values[0]] : ['in' => $values]];
    }

    public function indicator(mixed $state, ?string $model = null): ?string
    {
        $options = $this->options($model);
        $values = is_array($state) ? $state : [$state];
        $labels = array_map(static fn (mixed $value): string => is_string($value) ? (string) __($options[$value] ?? $value) : '', $values);

        return (string) __($this->label()).': '.implode(', ', $labels);
    }

    private function valueType(): ValueType
    {
        $source = $this->get(self::Options);
        $keys = is_array($source) ? array_keys($source) : null;

        if (is_string($source) && enum_exists($source) && is_subclass_of($source, BackedEnum::class)) {
            $keys = array_map(static fn (BackedEnum $case): int|string => $case->value, $source::cases());
        }

        if ($this->stringProperty(self::Relationship) !== null) {
            return ValueType::String;
        }

        return $keys !== null && $keys !== [] && array_filter($keys, is_int(...)) === $keys ? ValueType::Integer : ValueType::String;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Relationship, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-z][A-Za-z0-9_]{0,63}$/', $value) === 1, ruleDescription: 'a relation method name'),
            new PropertyDefinition(self::Title, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $value) === 1, ruleDescription: 'a column name'),
            new PropertyDefinition(self::Options, Type::mixed(), nullable: true, rule: static fn (mixed $value): bool => is_array($value) || $value instanceof Callback || (is_string($value) && enum_exists($value) && is_subclass_of($value, BackedEnum::class)), ruleDescription: 'a value => label map, a backed enum class or a Callback'),
            new PropertyDefinition(self::Multiple, Type::bool(), default: false),
        )->withConstraint(static function (ComponentInterface $filter): ?string {
            $options = $filter->has(self::Options) && $filter->get(self::Options) !== null;
            $relationship = $filter->has(self::Relationship);

            return $options === $relationship ? 'a SelectFilter needs either Options or a Relationship.' : null;
        });
    }
}
