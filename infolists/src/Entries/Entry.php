<?php

declare(strict_types=1);

namespace Gcego\Infolists\Entries;

use Gcego\Chroma\Tailwind;
use Gcego\Components\Component;
use Gcego\Components\Evaluation\Computed;
use Gcego\Components\Foundation\Makeable;
use Gcego\Components\Properties\Property;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Infolists\Building\EntryBuilder;
use Gcego\Infolists\Configuration\Access;
use Gcego\Infolists\Configuration\Format;
use Gcego\Infolists\Configuration\Resolve;
use Gcego\Infolists\Configuration\VisibleWhen;
use Gcego\Infolists\Contracts\InfolistComponent;
use Gcego\Infolists\InfolistsConfig;
use Gcego\Infolists\Presets\InfolistPresets;
use Gcego\Infolists\Support\RecordEvaluation;
use Gcego\Tables\Support\Formats;
use Gcego\Tables\Support\ValueResolver;
use Illuminate\Support\Str;
use WeakMap;

/**
 * The base of every read-only entry: a label and one value of the record.
 *
 *     new Text('name', 'Subject Name')
 *     new Text(new Property(Text::Name, 'name'), new Property(Text::Label, 'Subject Name'), new Property(Text::Copyable, true))
 *     new Text('category.name', 'Category')           // a trusted relation path: eager-loaded, scoped, never lazy
 *     Text::make('code')->copyable()->tooltip(fn (Subject $record): string => $record->name)
 *
 * The name is a trusted path into the record, never browser input. Values
 * are formatted to plain text or validated attributes; nothing stored in a
 * record is treated as HTML (rich text goes through SafeHtml).
 */
abstract class Entry extends Component implements InfolistComponent
{
    use Makeable;

    /**
     * Closure-valued properties per entry instance (entries are immutable).
     *
     * @var WeakMap<self, list<string>>|null
     */
    private static ?WeakMap $computedKeys = null;

    public const string NamePattern = '/^[a-z_][A-Za-z0-9_]{0,63}(?:\.[a-z_][A-Za-z0-9_]{0,63}){0,3}$/';

    /** Shown when the value is empty (default: gcego-infolists.formatting.empty_placeholder). */
    public const string Placeholder = 'placeholder';

    /** A copy-to-clipboard button for the displayed text. */
    public const string Copyable = 'copyable';

    /** A fixed tooltip for the value, or true for the full value when truncated. */
    public const string Tooltip = 'tooltip';

    /** Never shown. */
    public const string Hidden = 'hidden';

    /** Not shown when the value is empty. */
    public const string HiddenWhenEmpty = 'hidden_when_empty';

    /** Grid columns spanned: 1 to 4, or "full". */
    public const string ColumnSpan = 'column_span';

    /** Styling slots defined by infolists for entries (Tailwind::Label applies too). */
    public const string Value = 'value';

    public function entryName(): string
    {
        $name = $this->get(self::Name);

        return is_string($name) ? $name : '';
    }

    public function label(): string
    {
        $label = $this->get(self::Label);

        return is_string($label) ? $label : Str::headline(str_replace('.', ' ', $this->entryName()));
    }

    public function description(): ?string
    {
        $description = $this->get(self::Description);

        return is_string($description) ? $description : null;
    }

    /**
     * The relations this entry reads, relative to its record ("category" for "category.name").
     *
     * @return list<string>
     */
    public function relations(): array
    {
        $name = $this->entryName();

        return str_contains($name, '.') ? [substr($name, 0, (int) strrpos($name, '.'))] : [];
    }

    public function isHidden(): bool
    {
        return $this->get(self::Hidden) === true;
    }

    public function hidesWhenEmpty(): bool
    {
        return $this->get(self::HiddenWhenEmpty) === true;
    }

    public function access(): ?Access
    {
        $access = $this->configurations()->first(Access::class);

        return $access instanceof Access ? $access : null;
    }

    /**
     * @return list<VisibleWhen>
     */
    public function conditions(): array
    {
        return array_values(array_filter($this->configurations()->all(), static fn (mixed $node): bool => $node instanceof VisibleWhen));
    }

    /**
     * The value of this entry for a record: its Resolve node, or its path.
     */
    public function value(mixed $record): mixed
    {
        $resolve = $this->configurations()->first(Resolve::class);

        return $resolve instanceof Resolve ? $resolve->value($record) : ValueResolver::get($record, $this->entryName());
    }

    /**
     * This entry for one record: closure-valued properties (label, tooltip,
     * placeholder, a badge's variants, ...) are evaluated with $state,
     * $record, $root, $entry and $user, and checked against their definition.
     *
     * @param  list<string>|null  $only  Evaluate only these properties (masked entries resolve their label, never value-derived ones).
     */
    public function forRecord(mixed $state, mixed $record, mixed $root = null, ?array $only = null): static
    {
        self::$computedKeys ??= new WeakMap;
        $computed = self::$computedKeys[$this] ??= $this->computedKeys();

        if ($only !== null) {
            $computed = array_values(array_intersect($computed, $only));
        }

        if ($computed === []) {
            return $this;
        }

        $context = RecordEvaluation::context($record, ['state' => $state, 'root' => $root ?? $record, 'entry' => $this]);
        $resolved = [];
        $cleared = [];

        foreach ($computed as $key) {
            $value = $this->evaluateProperty($key, $context);

            if ($value === null) {
                $cleared[] = $key;
            } else {
                $resolved[] = new Property($key, $value);
            }
        }

        return $this->withoutProperty(...$cleared)->withProperty(...$resolved);
    }

    /**
     * @return list<string>
     */
    private function computedKeys(): array
    {
        $keys = [];

        foreach ($this->properties() as $key => $property) {
            if ($property->value instanceof Computed && static::definition()->property((string) $key)?->isCallback() !== true) {
                $keys[] = (string) $key;
            }
        }

        return $keys;
    }

    public function spanClass(int $columns): string
    {
        $span = $this->get(self::ColumnSpan);

        return is_int($span) || is_string($span) ? InfolistPresets::Spans[$span][$columns] ?? '' : '';
    }

    public function placeholder(InfolistsConfig $config): string
    {
        $placeholder = $this->get(self::Placeholder);

        return is_string($placeholder) ? Formats::translate($placeholder) : $config->placeholder;
    }

    /**
     * The Blade view of this entry's value (overridable in resources/views/vendor/gcego-infolists).
     */
    abstract public function view(): string;

    /**
     * View data for the value. Values are untrusted: formatted to plain text
     * (escaped by the view) or to validated attributes.
     *
     * @return array<string, mixed>
     */
    public function display(mixed $value, mixed $record, InfolistsConfig $config): array
    {
        $text = $this->text($value, $record, $config);
        $tooltip = $this->get(self::Tooltip);

        return [
            'text' => $text,
            'title' => is_string($tooltip) ? Formats::translate($tooltip) : null,
            'copy' => $this->get(self::Copyable) === true && $text !== null,
        ];
    }

    /**
     * Whether a display value counts as empty (for HiddenWhenEmpty).
     *
     * @param  array<string, mixed>  $display
     */
    public function isEmpty(array $display): bool
    {
        return ($display['text'] ?? null) === null;
    }

    public function text(mixed $value, mixed $record, InfolistsConfig $config): ?string
    {
        $format = $this->configurations()->first(Format::class);

        return $format instanceof Format ? $format->apply($value, $record, $this) : $this->format($value, $record, $config);
    }

    protected function format(mixed $value, mixed $record, InfolistsConfig $config): ?string
    {
        return Formats::text($value);
    }

    public static function builderClass(): string
    {
        return EntryBuilder::class;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('infolists.'.Str::kebab(class_basename(static::class)))
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: static fn (mixed $value): bool => is_string($value) && preg_match(self::NamePattern, $value) === 1, ruleDescription: 'an attribute name or relation path ("name", "category.name")'),
                new PropertyDefinition(self::Placeholder, Type::string()),
                new PropertyDefinition(self::Copyable, Type::bool(), default: false),
                new PropertyDefinition(self::Tooltip, [Type::bool(), Type::string()]),
                new PropertyDefinition(self::Hidden, Type::bool(), default: false),
                new PropertyDefinition(self::HiddenWhenEmpty, Type::bool(), default: false),
                new PropertyDefinition(self::ColumnSpan, [Type::int(), Type::string()], rule: static fn (mixed $value): bool => $value === 'full' || (is_int($value) && $value >= 1 && $value <= 4), ruleDescription: '1 to 4 or "full"'),
            )
            ->withoutChildren()
            ->withAllowedConfigurations([Format::class, Access::class, Resolve::class, VisibleWhen::class, Tailwind::class]);
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
        $map = [];

        foreach ((array) $this->get($key) as $index => $item) {
            $map[(string) $index] = $item;
        }

        return $map;
    }

    protected function lookup(string $key, mixed $value): mixed
    {
        $index = match (true) {
            $value instanceof \BackedEnum => (string) $value->value,
            $value instanceof \UnitEnum => $value->name,
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => null,
        };

        return $index === null ? null : ($this->mapProperty($key)[$index] ?? null);
    }
}
