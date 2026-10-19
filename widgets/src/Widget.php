<?php

declare(strict_types=1);

namespace Gcego\Widgets;

use Closure;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Data\Support\Callback;
use Gcego\Security\Contracts\Requirement;
use Gcego\Tables\Support\Icons;
use Gcego\Widgets\Configuration\Cache;
use Gcego\Widgets\Configuration\Refresh;
use Gcego\Widgets\Configuration\Trend;
use Gcego\Widgets\Data\Aggregate;
use Gcego\Widgets\Data\DataProvider;
use Gcego\Widgets\Data\DateRange;
use Gcego\Widgets\Data\Value;
use Illuminate\Support\Str;

/**
 * The base of every dashboard widget: a declarative definition, registered
 * in gcego/registry under "widget:<name>" (or nested in a group).
 *
 *     new Stat('Total Students', fn () => …)                    // closures become Value nodes, evaluated at render time
 *     new Stat(new Property(Stat::Name, 'total_students'), new Property(Stat::Label, 'Total Students'), new Aggregate(Student::class))
 *
 * Data comes from one of: a Value node or Value property, a DataProvider,
 * or an Aggregate, resolved by WidgetDataResolver when the widget renders,
 * never at construction or registration. Requirements (gcego/security
 * Permission, Ability, ...) are checked per request; a widget without any
 * requirement (on itself or its group) is denied.
 */
abstract class Widget extends Component
{
    /** A static value, or a WidgetDataProvider class (positional shorthand: new Stat('Label', $value)). */
    public const string Value = 'value';

    public const string Icon = 'icon';

    /** Layout metadata for gcego/layouts: columns spanned (1 to 4, or "full"). */
    public const string Span = 'span';

    /** Layout metadata: sm | md | lg | auto. */
    public const string Height = 'height';

    /** Layout metadata: the narrowest sensible width (xs | sm | md | lg). */
    public const string MinWidth = 'min_width';

    /** Empty-state text. */
    public const string Empty = 'empty';

    /** Always render through Livewire (refresh button), even without a DateRange or Refresh. */
    public const string Interactive = 'interactive';

    /** Styling slots defined by widgets (Tailwind::Wrapper, Header, Title, Description, Body, Footer, Icon apply too). */
    public const string ValueSlot = 'value';

    public const string TrendSlot = 'trend';

    public const string ChartSlot = 'chart';

    public const string LegendSlot = 'legend';

    public const string EmptySlot = 'empty';

    public const string LoadingSlot = 'loading';

    public const string NamePattern = '/^[a-z][a-z0-9_]{0,63}$/';

    public function __construct(mixed ...$items)
    {
        parent::__construct(...array_map(static fn (mixed $item): mixed => $item instanceof Closure ? new Value($item) : $item, $items));
    }

    public function widgetName(): string
    {
        $name = $this->get(self::Name);

        if (is_string($name)) {
            return $name;
        }

        $label = $this->get(self::Label);

        return is_string($label) ? mb_substr(Str::snake(Str::ascii(trim((string) preg_replace('/[^A-Za-z0-9 ]+/', ' ', $label)))), 0, 64) : '';
    }

    public function title(): string
    {
        $label = $this->get(self::Label);

        return is_string($label) ? $label : Str::headline($this->widgetName());
    }

    public function description(): ?string
    {
        $description = $this->get(self::Description);

        return is_string($description) ? $description : null;
    }

    /**
     * The Blade view of this widget (overridable in resources/views/vendor/gcego-widgets).
     */
    abstract public function view(): string;

    /**
     * Where the widget's data comes from: a Value node, a DataProvider, an Aggregate, or the Value property.
     */
    public function dataSource(): Value|DataProvider|Aggregate|Activity\ActivitySource|null
    {
        foreach ([Value::class, DataProvider::class, Aggregate::class] as $class) {
            $node = $this->configurations()->first($class);

            if ($node instanceof Value || $node instanceof DataProvider || $node instanceof Aggregate) {
                return $node;
            }
        }

        $value = $this->get(self::Value);

        return match (true) {
            $value instanceof Callback => new Value($value->closure),
            is_string($value) && is_subclass_of($value, Contracts\WidgetDataProvider::class) => new DataProvider($value),
            is_int($value), is_float($value), is_string($value) => new Value($value),
            default => null,
        };
    }

    /**
     * View data for this widget's template, from its resolved data. Built-in
     * widgets format their values here; custom widgets override it.
     *
     * @return array<string, mixed>
     */
    public function viewData(Data\WidgetDataResult $result, WidgetsConfig $config, Rendering\WidgetRenderFrame $frame): array
    {
        return ['result' => $result, 'empty' => $result->value === null && $result->labels === [] && $result->items === [] && $result->values === []];
    }

    public function dateRange(): ?DateRange
    {
        $range = $this->configurations()->first(DateRange::class);

        return $range instanceof DateRange ? $range : null;
    }

    public function refresh(): ?Refresh
    {
        $refresh = $this->configurations()->first(Refresh::class);

        return $refresh instanceof Refresh ? $refresh : null;
    }

    public function cache(): ?Cache
    {
        $cache = $this->configurations()->first(Cache::class);

        return $cache instanceof Cache ? $cache : null;
    }

    /**
     * @return list<Requirement>
     */
    public function requirements(): array
    {
        return array_values(array_filter($this->configurations()->all(), static fn (mixed $node): bool => $node instanceof Requirement));
    }

    /**
     * Whether rendering needs the Livewire host (state or refreshing). Static widgets render as plain Blade.
     */
    public function needsLivewire(): bool
    {
        if ($this->get(self::Interactive) === true || $this->dateRange() !== null || $this->refresh() !== null) {
            return true;
        }

        foreach ($this->children() as $child) {
            if ($child instanceof self && $child->needsLivewire()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Layout metadata for future gcego/layouts.
     *
     * @return array{span: int|string, height: string, min_width: string}
     */
    public function layout(): array
    {
        $span = $this->get(self::Span);
        $height = $this->get(self::Height);
        $min = $this->get(self::MinWidth);

        return [
            'span' => is_int($span) || is_string($span) ? $span : 1,
            'height' => is_string($height) ? $height : 'auto',
            'min_width' => is_string($min) ? $min : 'xs',
        ];
    }

    public function emptyText(): string
    {
        $empty = $this->get(self::Empty);

        return is_string($empty) ? $empty : 'gcego-widgets::messages.empty';
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('widgets.'.Str::kebab(class_basename(static::class)))
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(self::NamePattern, $value) === 1, ruleDescription: 'a lowercase snake_case name'),
                new PropertyDefinition(self::Value, [Type::int(), Type::float(), Type::string(), Type::instanceOf(Callback::class)]),
                new PropertyDefinition(self::Icon, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(Icons::NamePattern, $value) === 1, ruleDescription: 'an icon name'),
                new PropertyDefinition(self::Span, [Type::int(), Type::string()], default: 1, rule: static fn (mixed $value): bool => $value === 'full' || (is_int($value) && $value >= 1 && $value <= 4), ruleDescription: '1 to 4 or "full"'),
                new PropertyDefinition(self::Height, Type::string(), default: 'auto', allowedValues: ['sm', 'md', 'lg', 'auto']),
                new PropertyDefinition(self::MinWidth, Type::string(), default: 'xs', allowedValues: ['xs', 'sm', 'md', 'lg']),
                new PropertyDefinition(self::Empty, Type::string()),
                new PropertyDefinition(self::Interactive, Type::bool(), default: false),
            )
            ->withoutChildren()
            ->withAllowedConfigurations(self::widgetConfigurations())
            ->withConstraint(static function (ComponentInterface $widget): ?string {
                if (! $widget instanceof self) {
                    return null;
                }

                if ($widget->widgetName() === '' || preg_match(self::NamePattern, $widget->widgetName()) !== 1) {
                    return 'a widget needs a Name (or a Label to derive it from).';
                }

                $sources = count(array_filter([$widget->configurations()->first(Value::class), $widget->configurations()->first(DataProvider::class), $widget->configurations()->first(Aggregate::class)]));

                return $sources > 1 ? 'declare one data source (a Value, a DataProvider or an Aggregate).' : null;
            });
    }

    /**
     * Configuration nodes every widget accepts.
     *
     * @return list<class-string<ConfigurationNode>>
     */
    protected static function widgetConfigurations(): array
    {
        return [Value::class, DataProvider::class, Aggregate::class, DateRange::class, Refresh::class, Cache::class, Trend::class, Requirement::class, Tailwind::class];
    }

    /**
     * @return list<string>
     */
    protected static function shorthand(): array
    {
        return [self::Label, self::Value];
    }

    protected function stringProperty(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) ? $value : null;
    }
}
