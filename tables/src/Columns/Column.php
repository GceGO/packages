<?php

declare(strict_types=1);

namespace Gcego\Tables\Columns;

use BackedEnum;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Evaluation\Computed;
use Gcego\Components\Evaluation\EvaluationContext;
use Gcego\Components\Facades\Components;
use Gcego\Components\Foundation\Makeable;
use Gcego\Components\Properties\Property;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Data\Filters\Filterable;
use Gcego\Data\Support\FieldName;
use Gcego\Tables\Building\ColumnBuilder;
use Gcego\Tables\Configuration\Access;
use Gcego\Tables\Configuration\Format;
use Gcego\Tables\Contracts\TableComponent;
use Gcego\Tables\Presets\TablePresets;
use Gcego\Tables\Support\Formats;
use Gcego\Tables\Support\ValueResolver;
use Gcego\Tables\TablesConfig;
use Illuminate\Support\Str;
use UnitEnum;
use WeakMap;

/**
 * The base of every column: a declarative definition of how one value of a
 * row is shown, searched and sorted.
 *
 *     new Text('name', 'Subject Name')
 *     new Text(new Property(Text::Name, 'name'), new Property(Text::Searchable, true), new Property(Text::Sortable, true))
 *     new Text('category.name', 'Category')            // a relation path: eager-loaded, never lazy
 *     Text::make('name')->searchable()->sortable()->tooltip(fn (Subject $record): string => $record->code)
 *     Text::make('full_name')->state(fn (Teacher $record): string => $record->first_name.' '.$record->last_name)
 *
 * The name is a trusted path into the row; the browser only ever refers to
 * columns by these names, and they are checked against the definition.
 * Columns only format values: every value is escaped, nothing a record
 * contains is treated as HTML.
 */
abstract class Column extends Component implements TableComponent
{
    use Makeable;

    /** fn (mixed $record): mixed computes the cell value instead of reading the column name from the row. */
    public const string StateUsing = 'state_using';

    /** fn (?Authenticatable $user): bool, once per request: false removes the column (not queried, sorted, searched or exported). */
    public const string VisibleUsing = 'visible_using';

    /** fn (?Authenticatable $user): bool, once per request: true removes the column. */
    public const string HiddenUsing = 'hidden_using';

    /**
     * Closure-valued presentation properties per column instance (columns are immutable).
     *
     * @var WeakMap<self, list<string>>|null
     */
    private static ?WeakMap $computedKeys = null;

    /**
     * Properties that shape the query and the column manager; they cannot vary per row.
     */
    public const array Structural = [self::Name, self::Searchable, self::Sortable, self::SortColumn, self::Hideable, self::Visible, self::VisibleFrom, self::Exportable];

    public const string NamePattern = '/^[a-z_][A-Za-z0-9_]{0,63}(?:\.[a-z_][A-Za-z0-9_]{0,63}){0,3}$/';

    /** true searches the column itself; a list of fields searches those instead ("first_name", "last_name"). */
    public const string Searchable = 'searchable';

    public const string Sortable = 'sortable';

    /** The source column to sort by (required to sort a relation path or computed column). */
    public const string SortColumn = 'sort_column';

    /** false: always shown (users cannot hide it). */
    public const string Hideable = 'hideable';

    /** false: hidden until the user shows it. */
    public const string Visible = 'visible';

    /** start | center | end */
    public const string Align = 'align';

    /** xs | sm | md | lg | xl | auto */
    public const string Width = 'width';

    public const string Wrap = 'wrap';

    /** Shorten the text to this many characters (the full text stays available as a tooltip). */
    public const string Limit = 'limit';

    /** A fixed tooltip, or true for the full value when truncated. */
    public const string Tooltip = 'tooltip';

    /** Shown for empty values (default "—"). */
    public const string Placeholder = 'placeholder';

    /** sm | md | lg | xl: hide the column below this breakpoint. */
    public const string VisibleFrom = 'visible_from';

    public const string Exportable = 'exportable';

    public const array Alignments = ['start', 'center', 'end'];

    public const array Breakpoints = ['sm', 'md', 'lg', 'xl'];

    /**
     * The cell value for a row: the state_using closure, or the column name read from the row.
     */
    public function value(mixed $record): mixed
    {
        $state = $this->properties()->get(self::StateUsing)?->value;

        return $state instanceof Computed
            ? Components::evaluator()->evaluate($state, $this->rowContext(null, $record), $this, sprintf('state() of column "%s"', $this->columnName()))
            : ValueResolver::get($record, $this->columnName());
    }

    /**
     * This column for one row: every closure-valued presentation property
     * (tooltip, placeholder, a badge's variants, a link's url, ...) is
     * evaluated with $state, $record and $column and checked against its
     * definition. Columns without closures are returned as they are.
     */
    public function forRow(mixed $state, mixed $record): static
    {
        self::$computedKeys ??= new WeakMap;
        $computed = self::$computedKeys[$this] ??= $this->computedPresentationKeys();

        if ($computed === []) {
            return $this;
        }

        $context = null;
        $resolved = [];
        $cleared = [];

        foreach ($computed as $key) {
            $context ??= $this->rowContext($state, $record);
            $value = $this->evaluateProperty($key, $context);

            if ($value === null) {
                $cleared[] = $key;
            } else {
                $resolved[] = new Property($key, $value);
            }
        }

        if ($resolved === [] && $cleared === []) {
            return $this;
        }

        return $this->withoutProperty(...$cleared)->withProperty(...$resolved);
    }

    /**
     * Whether the column exists for this request (visible()/hidden() closures).
     *
     * @param  array<string, mixed>  $parameters  Named injections, such as the user.
     */
    public function isPresent(array $parameters = []): bool
    {
        $context = EvaluationContext::make($parameters)->withTyped(...array_values(array_filter($parameters, is_object(...))));

        foreach ([self::VisibleUsing => false, self::HiddenUsing => true] as $key => $removedWhen) {
            $condition = $this->get($key);

            if ($condition instanceof Computed && (Components::evaluator()->evaluate($condition, $context, $this, sprintf('%s of column "%s"', $key, $this->columnName())) === true) === $removedWhen) {
                return false;
            }
        }

        return true;
    }

    public function hasComputedState(): bool
    {
        return $this->get(self::StateUsing) instanceof Computed;
    }

    public function columnName(): string
    {
        $name = $this->get(self::Name);

        return is_string($name) ? $name : '';
    }

    public function label(): string
    {
        $label = $this->evaluateProperty(self::Label, ['column' => $this]);

        return is_string($label) ? $label : Str::headline(str_replace('.', ' ', $this->columnName()));
    }

    public function isRelationPath(): bool
    {
        return str_contains($this->columnName(), '.');
    }

    /**
     * The relation to eager-load for this column ("category" for "category.name"), or null.
     */
    public function relation(): ?string
    {
        $name = $this->columnName();

        return $this->isRelationPath() ? substr($name, 0, (int) strrpos($name, '.')) : null;
    }

    /**
     * Relations this column reads (eager-loaded by the table).
     *
     * @return list<string>
     */
    public function relations(): array
    {
        $relation = $this->relation();

        return $relation === null ? [] : [$relation];
    }

    public function isSearchable(): bool
    {
        $searchable = $this->get(self::Searchable);

        return $searchable === true || (is_array($searchable) && $searchable !== []);
    }

    /**
     * The source fields searched for this column.
     *
     * @return list<string>
     */
    public function searchFields(): array
    {
        $searchable = $this->get(self::Searchable);

        return match (true) {
            is_array($searchable) => array_values(array_filter($searchable, is_string(...))),
            $searchable === true => [$this->columnName()],
            default => [],
        };
    }

    public function isSortable(): bool
    {
        return $this->get(self::Sortable) === true;
    }

    /**
     * The key users sort by (the column name).
     */
    public function sortKey(): string
    {
        return $this->columnName();
    }

    public function sortColumn(): string
    {
        $column = $this->get(self::SortColumn);

        return is_string($column) ? $column : $this->columnName();
    }

    public function isHideable(): bool
    {
        return $this->get(self::Hideable) !== false;
    }

    public function isVisibleByDefault(): bool
    {
        return $this->get(self::Visible) !== false;
    }

    public function isExportable(): bool
    {
        return $this->get(self::Exportable) !== false;
    }

    public function access(): ?Access
    {
        $access = $this->configurations()->first(Access::class);

        return $access instanceof Access ? $access : null;
    }

    /**
     * Header and cell classes from the column's layout properties (alignment, width, wrapping, breakpoint).
     */
    public function layoutClasses(): string
    {
        $align = $this->get(self::Align);
        $width = $this->get(self::Width);
        $from = $this->get(self::VisibleFrom);

        return implode(' ', array_filter([
            TablePresets::Align[is_string($align) ? $align : $this->defaultAlign()] ?? null,
            is_string($width) ? TablePresets::Widths[$width] ?? null : null,
            $this->get(self::Wrap) === true ? 'whitespace-normal break-words' : 'whitespace-nowrap',
            is_string($from) ? TablePresets::VisibleFrom[$from] ?? null : null,
        ]));
    }

    /**
     * @return list<string>
     */
    private function computedPresentationKeys(): array
    {
        $keys = [];

        foreach ($this->properties() as $key => $property) {
            if ($property->value instanceof Computed && static::definition()->property((string) $key)?->isCallback() !== true) {
                $keys[] = (string) $key;
            }
        }

        return $keys;
    }

    private function rowContext(mixed $state, mixed $record): EvaluationContext
    {
        return EvaluationContext::make(['state' => $state, 'record' => $record, 'column' => $this])
            ->withTyped(...(is_object($record) ? [$record] : []));
    }

    public function placeholder(): string
    {
        $placeholder = $this->get(self::Placeholder);

        return is_string($placeholder) ? $placeholder : '—';
    }

    /**
     * The Blade view for this column's cells (overridable in resources/views/vendor/gcego-tables).
     */
    abstract public function cellView(): string;

    /**
     * View data for one cell. Values are untrusted: they are formatted to
     * plain text (escaped by the view) or to validated attributes.
     *
     * @return array<string, mixed>
     */
    public function cellData(mixed $value, mixed $record, TablesConfig $config): array
    {
        $text = $this->text($value, $record, $config);
        $limit = $this->get(self::Limit);
        $short = $text !== null && is_int($limit) && mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, $limit)).'…' : $text;
        $tooltip = $this->get(self::Tooltip);

        return [
            'text' => $short,
            'title' => is_string($tooltip) ? self::translate($tooltip) : ($tooltip === true || $short !== $text ? $text : null),
        ];
    }

    /**
     * Plain text for a value (used by text-like cells and exports).
     */
    public function text(mixed $value, mixed $record, TablesConfig $config): ?string
    {
        $format = $this->configurations()->first(Format::class);

        if ($format instanceof Format) {
            return $format->apply($value, $record, $this);
        }

        return $this->format($value, $record, $config);
    }

    /**
     * The value written to exports: plain text, never markup.
     */
    public function exportValue(mixed $value, mixed $record, TablesConfig $config): string|int|float|null
    {
        return $this->text($value, $record, $config);
    }

    /**
     * Type-specific formatting (no Format node).
     */
    protected function format(mixed $value, mixed $record, TablesConfig $config): ?string
    {
        return self::stringify($value);
    }

    protected function defaultAlign(): string
    {
        return 'start';
    }

    /**
     * Any value as plain text: enums by label() or value, lists joined, dates in ISO form.
     */
    public static function stringify(mixed $value): ?string
    {
        return Formats::text($value);
    }

    /**
     * A translated string (the key itself when the translation is not a string).
     */
    public static function translate(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }

    public static function builderClass(): string
    {
        return ColumnBuilder::class;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $one = static fn (array $allowed): \Closure => static fn (mixed $value): bool => is_string($value) && in_array($value, $allowed, true);

        return parent::defineSchema()
            ->withType('tables.'.Str::kebab(class_basename(static::class)))
            ->withoutProperties(self::Id, self::Description)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: static fn (mixed $value): bool => is_string($value) && preg_match(self::NamePattern, $value) === 1, ruleDescription: 'a column name or relation path ("name", "category.name")'),
                new PropertyDefinition(self::Searchable, [Type::bool(), Type::list()], default: false, rule: static fn (mixed $value): bool => is_bool($value) || (is_array($value) && $value !== [] && array_filter($value, static fn (mixed $field): bool => ! is_string($field) || ! FieldName::isValid($field)) === []), ruleDescription: 'true or a list of field names'),
                new PropertyDefinition(self::Sortable, Type::bool(), default: false),
                new PropertyDefinition(self::SortColumn, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $value) === 1, ruleDescription: 'a column of the source itself (no relation paths)'),
                new PropertyDefinition(self::Hideable, Type::bool(), default: true),
                new PropertyDefinition(self::Visible, Type::bool(), default: true),
                new PropertyDefinition(self::Align, Type::string(), rule: $one(self::Alignments), ruleDescription: 'start, center or end'),
                new PropertyDefinition(self::Width, Type::string(), rule: $one(array_keys(TablePresets::Widths)), ruleDescription: 'xs, sm, md, lg, xl or auto'),
                new PropertyDefinition(self::Wrap, Type::bool(), default: false),
                new PropertyDefinition(self::Limit, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= 1000, ruleDescription: 'between 1 and 1000'),
                new PropertyDefinition(self::Tooltip, [Type::bool(), Type::string()]),
                new PropertyDefinition(self::Placeholder, Type::string()),
                new PropertyDefinition(self::VisibleFrom, Type::string(), rule: $one(self::Breakpoints), ruleDescription: 'sm, md, lg or xl'),
                new PropertyDefinition(self::Exportable, Type::bool(), default: true),
                new PropertyDefinition(self::StateUsing, Type::instanceOf(Computed::class)),
                new PropertyDefinition(self::VisibleUsing, Type::instanceOf(Computed::class)),
                new PropertyDefinition(self::HiddenUsing, Type::instanceOf(Computed::class)),
            )
            ->withoutChildren()
            ->withAllowedConfigurations([Format::class, Access::class, Tailwind::class])
            ->withConstraint(static function (ComponentInterface $column): ?string {
                if (! $column instanceof self) {
                    return null;
                }

                foreach (self::Structural as $key) {
                    if ($column->isComputed($key)) {
                        return sprintf('column "%s": %s shapes the query and cannot be a closure; use visible(fn) or hidden(fn) to remove the column for some users.', $column->columnName(), $key);
                    }
                }

                if ($column->hasComputedState() && $column->get(self::Searchable) === true) {
                    return sprintf('column "%s" has a computed state; search it through searchable([...source fields]).', $column->columnName());
                }

                if ($column->hasComputedState() && $column->isSortable() && $column->get(self::SortColumn) === null) {
                    return sprintf('column "%s" has a computed state; sort it through a SortColumn of the source.', $column->columnName());
                }

                return null;
            })
            ->withConstraint(static function (ComponentInterface $column): ?string {
                if (! $column instanceof self || ! $column->isSortable()) {
                    return null;
                }

                if (preg_match(Filterable::KeyPattern, $column->sortKey()) !== 1) {
                    return sprintf('sortable column "%s" needs a lowercase snake_case name.', $column->columnName());
                }

                return $column->isRelationPath() && $column->get(self::SortColumn) === null
                    ? sprintf('column "%s" is a relation path; sort it through a SortColumn of the source (relation paths cannot be sorted).', $column->columnName())
                    : null;
            });
    }

    /**
     * @return list<string>
     */
    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }

    protected function stringProperty(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) ? $value : null;
    }

    protected function intProperty(string $key): ?int
    {
        $value = $this->get($key);

        return is_int($value) ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapProperty(string $key): array
    {
        $value = $this->get($key);
        $map = [];

        foreach (is_array($value) ? $value : [] as $index => $item) {
            $map[(string) $index] = $item;
        }

        return $map;
    }

    /**
     * A map value for a cell value (enums by value).
     */
    protected function lookup(string $key, mixed $value): mixed
    {
        $map = $this->mapProperty($key);
        $index = match (true) {
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof UnitEnum => $value->name,
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => null,
        };

        return $index === null ? null : ($map[$index] ?? null);
    }
}
