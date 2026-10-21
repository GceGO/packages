<?php

declare(strict_types=1);

namespace Gcego\Components;

use BackedEnum;
use Closure;
use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Collections\ComponentCollection;
use Gcego\Components\Collections\ConfigurationCollection;
use Gcego\Components\Collections\PropertyCollection;
use Gcego\Components\Contracts\Buildable;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Evaluation\Computed;
use Gcego\Components\Evaluation\EvaluationContext;
use Gcego\Components\Exceptions\DuplicatePropertyException;
use Gcego\Components\Exceptions\EvaluationException;
use Gcego\Components\Exceptions\InvalidBuilderException;
use Gcego\Components\Exceptions\InvalidComponentException;
use Gcego\Components\Exceptions\MissingPropertyException;
use Gcego\Components\Facades\Components;
use Gcego\Components\Layout\Item;
use Gcego\Components\Properties\Property;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Components\Schema\TypeKind;
use Gcego\Components\Support\ConstructorArguments;
use Gcego\Components\Support\Presentation;
use Gcego\Components\Trees\ComponentTree;
use Gcego\Components\Validation\ValidationResult;
use UnitEnum;
use WeakReference;

/**
 * @method static ComponentBuilder container(string $name)
 * @method static ComponentBuilder grid(string $name)
 * @method static ComponentBuilder stack(string $name)
 * @method static ComponentBuilder row(string $name)
 * @method static ComponentBuilder column(string $name)
 * @method static ComponentBuilder card(string $name)
 * @method static ComponentBuilder panel(string $name)
 * @method static ComponentBuilder tabs(string $name)
 * @method static ComponentBuilder tab(string $name)
 * @method static ComponentBuilder accordion(string $name)
 * @method static ComponentBuilder accordionItem(string $name)
 * @method static ComponentBuilder fieldset(string $name)
 * @method static ComponentBuilder modal(string $name)
 * @method static ComponentBuilder drawer(string $name)
 * @method static ComponentBuilder splitter(string $name)
 * @method static ComponentBuilder wizard(string $name)
 * @method static ComponentBuilder wizardStep(string $name)
 * @method static ComponentBuilder form(string $name)
 * @method static ComponentBuilder textInput(string $name)
 * @method static ComponentBuilder textarea(string $name)
 * @method static ComponentBuilder numberInput(string $name)
 * @method static ComponentBuilder emailInput(string $name)
 * @method static ComponentBuilder passwordInput(string $name)
 * @method static ComponentBuilder searchInput(string $name)
 * @method static ComponentBuilder urlInput(string $name)
 * @method static ComponentBuilder telInput(string $name)
 * @method static ComponentBuilder select(string $name)
 * @method static ComponentBuilder multiSelect(string $name)
 * @method static ComponentBuilder checkbox(string $name)
 * @method static ComponentBuilder radio(string $name)
 * @method static ComponentBuilder toggle(string $name)
 * @method static ComponentBuilder datePicker(string $name)
 * @method static ComponentBuilder dateTimePicker(string $name)
 * @method static ComponentBuilder timePicker(string $name)
 * @method static ComponentBuilder fileUpload(string $name)
 * @method static ComponentBuilder hiddenInput(string $name)
 * @method static ComponentBuilder colorPicker(string $name)
 * @method static ComponentBuilder rangeInput(string $name)
 * @method static ComponentBuilder richEditor(string $name)
 * @method static ComponentBuilder codeEditor(string $name)
 * @method static ComponentBuilder table(string $name)
 * @method static ComponentBuilder tableColumn(string $name)
 * @method static ComponentBuilder text(string $name)
 * @method static ComponentBuilder heading(string $name)
 * @method static ComponentBuilder badge(string $name)
 * @method static ComponentBuilder avatar(string $name)
 * @method static ComponentBuilder icon(string $name)
 * @method static ComponentBuilder stat(string $name)
 * @method static ComponentBuilder keyValueList(string $name)
 * @method static ComponentBuilder descriptionList(string $name)
 * @method static ComponentBuilder listView(string $name)
 * @method static ComponentBuilder listItem(string $name)
 * @method static ComponentBuilder progress(string $name)
 * @method static ComponentBuilder timeline(string $name)
 * @method static ComponentBuilder timelineItem(string $name)
 * @method static ComponentBuilder codeBlock(string $name)
 * @method static ComponentBuilder menu(string $name)
 * @method static ComponentBuilder menuItem(string $name)
 * @method static ComponentBuilder breadcrumbs(string $name)
 * @method static ComponentBuilder breadcrumb(string $name)
 * @method static ComponentBuilder pagination(string $name)
 * @method static ComponentBuilder link(string $name)
 * @method static ComponentBuilder alert(string $name)
 * @method static ComponentBuilder toast(string $name)
 * @method static ComponentBuilder banner(string $name)
 * @method static ComponentBuilder emptyState(string $name)
 * @method static ComponentBuilder skeleton(string $name)
 * @method static ComponentBuilder spinner(string $name)
 * @method static ComponentBuilder tooltip(string $name)
 * @method static ComponentBuilder popover(string $name)
 * @method static ComponentBuilder button(string $name)
 * @method static ComponentBuilder buttonGroup(string $name)
 * @method static ComponentBuilder dropdown(string $name)
 * @method static ComponentBuilder dropdownItem(string $name)
 * @method static ComponentBuilder image(string $name)
 * @method static ComponentBuilder video(string $name)
 * @method static ComponentBuilder audio(string $name)
 * @method static ComponentBuilder embed(string $name)
 * @method static ComponentBuilder map(string $name)
 * @method static ComponentBuilder chart(string $name)
 * @method static ComponentBuilder calendar(string $name)
 *
 * The base of every GCEGO component.
 *
 * A component is declared fluently through its builder:
 *
 *     Text::define('name', 'Subject Name')->searchable()->sortable()
 *
 * or by passing items to its constructor:
 *
 *     new Component(
 *         new Property(Component::Name, 'dashboard'),
 *         new Component(new Property(Component::Name, 'statistics')),
 *     );
 *
 * Properties, child components and configuration nodes are told apart by their
 * contracts. Subclasses describe themselves by overriding defineSchema() and,
 * optionally, shorthand(). Instances are immutable; the with*() methods return
 * modified copies of the same concrete class.
 *
 * Construction enforces structural rules (supported items, unique property
 * keys, safe values). Schema rules (required, types, unknown properties,
 * children) are checked by validate() / assertValid() over the whole tree so
 * every error can be reported with its path.
 */
class Component implements ComponentInterface
{
    public const string Name = 'name';

    public const string Label = 'label';

    public const string Description = 'description';

    public const string Id = 'id';

    /**
     * Explicit ids must start with a letter and contain only letters, digits, "_", "-", "." or ":".
     */
    public const string IdPattern = '/\A[A-Za-z][A-Za-z0-9_.:\-]*\z/';

    private PropertyCollection $properties;

    private ComponentCollection $children;

    private ConfigurationCollection $configurations;

    private ?ValidationResult $validationResult = null;

    /**
     * @var WeakReference<ComponentManager>|null
     */
    private ?WeakReference $validationManager = null;

    /**
     * @param  Property|ComponentInterface|ConfigurationNode|scalar|UnitEnum|array<mixed>|null  ...$items
     *                                                                                                     Leading non-item values are mapped to the keys returned by shorthand().
     */
    public function __construct(mixed ...$items)
    {
        Components::getFacadeRoot();

        $parsed = ConstructorArguments::parse(static::class, static::shorthand(), $items);

        try {
            $this->properties = new PropertyCollection(...self::castBackedEnums($parsed->properties));
        } catch (DuplicatePropertyException $exception) {
            throw DuplicatePropertyException::forKey(self::duplicateKey($parsed->properties), static::class, $exception);
        }

        $this->children = new ComponentCollection(...$parsed->children);
        $this->configurations = new ConfigurationCollection(...$parsed->configurations);
    }

    /**
     * Start a fluent definition of this component.
     *
     * Leading positional values follow shorthand(); named values set the
     * property their name maps to (field: 'user_id' sets Field); children,
     * builders, properties and configuration nodes are accepted as in the
     * constructor. The builder is built when it is placed in a parent.
     */
    public static function define(mixed ...$items): ComponentBuilder
    {
        return Components::define(static::class, $items);
    }

    /** @param array<int|string, mixed> $arguments */
    public static function __callStatic(string $method, array $arguments): ComponentBuilder
    {
        if (count($arguments) !== 1 || ! is_string(reset($arguments)) || (array_keys($arguments) !== [0] && array_keys($arguments) !== ['name'])) {
            throw InvalidBuilderException::invalidValue(static::class, $method, 'Expected exactly one string name.');
        }

        return Components::component($method, reset($arguments));
    }

    public static function section(string $name): ComponentBuilder
    {
        return Components::component('section', $name);
    }

    public static function group(string $name): ComponentBuilder
    {
        return Components::component('group', $name);
    }

    public static function item(string $name): ComponentBuilder
    {
        return Components::component('item', $name);
    }

    /**
     * @param  iterable<ComponentInterface|Buildable|iterable<mixed>|null>  $components
     */
    public static function collect(iterable $components): ComponentCollection
    {
        return Components::collect($components);
    }

    /**
     * Configure every builder of this class (and its subclasses) created
     * through make(), typically from a service provider's boot():
     *
     *     TextInput::configureUsing(fn (ComponentBuilder $input) => $input->maxLength(255));
     *
     * With $during, the modifier applies only while that callback runs.
     * Important modifiers run after the others.
     *
     * @param  Closure(ComponentBuilder): ComponentBuilder  $modifier
     */
    public static function configureUsing(Closure $modifier, ?Closure $during = null, bool $isImportant = false): mixed
    {
        return Components::configurator()->configureUsing(static::class, $modifier, $during, $isImportant);
    }

    /**
     * Add a fluent method to the builders of this class and its subclasses.
     * The macro is bound to the builder and returns the changed copy:
     *
     *     TextInput::macro('slug', fn () => $this->maxLength(64)->helpText('Lowercase, dashes only.'));
     */
    public static function macro(string $name, Closure $macro): void
    {
        Components::configurator()->macro(static::class, $name, $macro);
    }

    public static function hasMacro(string $name): bool
    {
        return Components::configurator()->macroFor(static::class, $name) !== null;
    }

    /**
     * The builder define() returns. Override to give a component richer fluent methods.
     *
     * @return class-string<ComponentBuilder>
     */
    public static function builderClass(): string
    {
        return ComponentBuilder::class;
    }

    /**
     * The property keys positional define()/constructor values are assigned to.
     *
     * @return list<string>
     */
    final public static function shorthandKeys(): array
    {
        return static::shorthand();
    }

    /**
     * The schema of this class, cached within the active provider scope.
     */
    final public static function definition(): ComponentDefinition
    {
        return Components::getFacadeRoot()->rememberDefinition(static::class, static::defineSchema(...));
    }

    /**
     * Describe the schema of this component class.
     *
     * Override and extend the parent definition:
     *
     *     protected static function defineSchema(): ComponentDefinition
     *     {
     *         return parent::defineSchema()
     *             ->withType('tables.column.text')
     *             ->withProperties(new PropertyDefinition(self::Searchable, Type::bool(), default: false))
     *             ->withoutChildren();
     *     }
     *
     * The generic base Component accepts unknown properties; every subclass
     * rejects them unless it calls withUnknownProperties().
     */
    protected static function defineSchema(): ComponentDefinition
    {
        return new ComponentDefinition(
            type: str_contains(static::class, '@anonymous') ? 'anonymous' : static::class,
            properties: [
                new PropertyDefinition(
                    self::Name,
                    Type::string(),
                    rule: static fn (mixed $value): bool => $value !== '',
                    ruleDescription: 'non-empty string',
                ),
                new PropertyDefinition(self::Label, Type::string()),
                new PropertyDefinition(self::Description, Type::string()),
                new PropertyDefinition(
                    self::Id,
                    Type::string(),
                    rule: static fn (mixed $value): bool => is_string($value) && preg_match(self::IdPattern, $value) === 1,
                    ruleDescription: 'identifier matching '.self::IdPattern,
                ),
            ],
            allowsUnknownProperties: static::class === self::class,
        );
    }

    /**
     * Preconfigure builders created through make(), before configureUsing() modifiers run:
     *
     *     protected static function setUp(ComponentBuilder $builder): ComponentBuilder
     *     {
     *         return $builder->maxLength(255);
     *     }
     */
    protected static function setUp(ComponentBuilder $builder): ComponentBuilder
    {
        return $builder;
    }

    /**
     * Property keys that leading positional constructor values are assigned to.
     *
     * Returning [self::Name, self::Label] enables new Text('name', 'Subject Name').
     *
     * @return list<string>
     */
    protected static function shorthand(): array
    {
        return [];
    }

    final public function type(): string
    {
        return static::definition()->type;
    }

    final public function id(): ?string
    {
        $id = $this->properties->value(self::Id);

        return is_string($id) ? $id : null;
    }

    final public function name(): ?string
    {
        $name = $this->get(self::Name);

        return is_string($name) ? $name : null;
    }

    final public function properties(): PropertyCollection
    {
        return $this->properties;
    }

    final public function get(string $key, mixed $fallback = null): mixed
    {
        $property = $this->properties->get($key);

        if ($property !== null) {
            return $property->value;
        }

        $definition = static::definition()->property($key);

        return $definition !== null && $definition->hasDefault ? $definition->default : $fallback;
    }

    /**
     * Like get(), but fails loudly when there is neither a value nor a default.
     *
     * @throws MissingPropertyException
     */
    final public function require(string $key): mixed
    {
        if (! $this->has($key)) {
            throw MissingPropertyException::forKey($key, $this->type(), static::class);
        }

        return $this->get($key);
    }

    final public function has(string $key): bool
    {
        return $this->properties->has($key) || static::definition()->property($key)?->hasDefault === true;
    }

    final public function hasExplicit(string $key): bool
    {
        return $this->properties->has($key);
    }

    /**
     * @return array<string, mixed>
     */
    final public function defaults(): array
    {
        return static::definition()->defaults();
    }

    final public function effectiveProperties(): array
    {
        $effective = array_replace(static::definition()->defaults(), $this->properties->toArray());
        ksort($effective);

        return $effective;
    }

    final public function children(): ComponentCollection
    {
        return $this->children;
    }

    final public function childCount(): int
    {
        return $this->children->count();
    }

    final public function hasChildren(): bool
    {
        return ! $this->children->isEmpty();
    }

    final public function configurations(): ConfigurationCollection
    {
        return $this->configurations;
    }

    /**
     * Whether a property holds a closure that is only resolved by evaluateProperty().
     */
    final public function isComputed(string $key): bool
    {
        return $this->get($key) instanceof Computed;
    }

    /**
     * The value of a property, calling it when it is computed.
     *
     * Closure parameters are injected by name and type from the context, plus
     * $component and, when the context carries state, $get, $set and $state.
     * A null result falls back to the property's default. Any other result is
     * checked against the property definition, like a declared value.
     * Callback properties (such as afterStateUpdated) are returned uncalled.
     *
     * @param  EvaluationContext|array<string, mixed>  $context
     *
     * @throws EvaluationException
     */
    final public function evaluateProperty(string $key, EvaluationContext|array $context = []): mixed
    {
        $value = $this->get($key);

        if (! $value instanceof Computed) {
            return $value;
        }

        $definition = static::definition()->property($key);

        if ($definition?->isCallback() === true) {
            return $value;
        }

        $subject = sprintf('property "%s" on component "%s"%s', $key, $this->type(), $this->name() === null ? '' : ' ('.$this->name().')');
        $result = Components::evaluator()->evaluate($value, EvaluationContext::from($context), $this, $subject);

        if ($result === null || $definition === null) {
            return $result ?? ($definition?->hasDefault === true ? $definition->default : null);
        }

        $expected = match (true) {
            ! $definition->acceptsType($result) => $definition->expectedType(),
            ! $definition->allowsValue($result) => $definition->expectedValues(),
            ! $definition->satisfiesRule($result) => $definition->expectedRule(),
            default => null,
        };

        if ($expected !== null) {
            throw EvaluationException::invalidResult($subject, $expected, $result);
        }

        return $result;
    }

    /**
     * Every effective property (explicit values over defaults), evaluated.
     *
     * @param  EvaluationContext|array<string, mixed>  $context
     * @return array<string, mixed>
     */
    final public function evaluatedProperties(EvaluationContext|array $context = []): array
    {
        $context = EvaluationContext::from($context);
        $evaluated = [];

        foreach (array_keys($this->effectiveProperties()) as $key) {
            $evaluated[$key] = $this->evaluateProperty($key, $context);
        }

        return $evaluated;
    }

    /**
     * Hidden by hidden(), visible(false), hiddenOn() for the context's
     * operation, or visibleOn() for any other operation.
     *
     * @param  EvaluationContext|array<string, mixed>  $context
     */
    final public function isHiddenIn(EvaluationContext|array $context = []): bool
    {
        $context = EvaluationContext::from($context);
        $properties = [];

        foreach (['hidden', 'visible', 'hiddenOn', 'visibleOn'] as $key) {
            $properties[$key] = $this->evaluateProperty($key, $context);
        }

        return Presentation::isHidden($properties, $context->operation());
    }

    /**
     * @param  EvaluationContext|array<string, mixed>  $context
     */
    final public function isVisibleIn(EvaluationContext|array $context = []): bool
    {
        return ! $this->isHiddenIn($context);
    }

    /**
     * The evaluated label, or one derived from the name: "billing.first_name" reads "First name".
     *
     * @param  EvaluationContext|array<string, mixed>  $context
     */
    final public function displayLabel(EvaluationContext|array $context = []): ?string
    {
        return Presentation::label($this->evaluateProperty(self::Label, $context), $this->name());
    }

    /**
     * Validate this component and all descendants, memoized within the active scope.
     */
    final public function validate(): ValidationResult
    {
        $manager = Components::getFacadeRoot();

        if ($this->validationResult === null || $this->validationManager?->get() !== $manager) {
            $this->validationResult = $manager->validate($this);
            $this->validationManager = WeakReference::create($manager);
        }

        return $this->validationResult;
    }

    /**
     * @throws InvalidComponentException
     */
    final public function assertValid(): static
    {
        $this->validate()->throwIfInvalid();

        return $this;
    }

    final public function normalize(): array
    {
        return Components::normalize($this);
    }

    final public function tree(): ComponentTree
    {
        return Components::tree($this);
    }

    /**
     * Copy with the given properties added or replaced.
     */
    final public function withProperty(Property ...$properties): static
    {
        $copy = clone $this;
        $copy->properties = $this->properties->with(...self::castBackedEnums($properties));

        return $copy;
    }

    final public function withoutProperty(string ...$keys): static
    {
        $copy = clone $this;
        $copy->properties = $this->properties->without(...$keys);

        return $copy;
    }

    /**
     * Copy with the children replaced.
     */
    final public function withChildren(ComponentInterface ...$children): static
    {
        $copy = clone $this;
        $copy->children = new ComponentCollection(...$children);

        return $copy;
    }

    /**
     * Copy with children appended after the existing ones.
     */
    final public function withAppendedChildren(ComponentInterface ...$children): static
    {
        $copy = clone $this;
        $copy->children = new ComponentCollection(...$this->children->all(), ...$children);

        return $copy;
    }

    /**
     * Copy with the configuration nodes replaced.
     */
    final public function withConfigurations(ConfigurationNode ...$configurations): static
    {
        $copy = clone $this;
        $copy->configurations = new ConfigurationCollection(...$configurations);

        return $copy;
    }

    final public function __clone()
    {
        $this->validationResult = null;
        $this->validationManager = null;
    }

    /**
     * A string-backed enum case given to a property that takes strings but not
     * that enum is stored as its value: new Property(Resource::Icon, Heroicon::Home)
     * holds "heroicon:home". Properties typed for the enum keep the case.
     *
     * @param  array<array-key, Property>  $properties
     * @return list<Property>
     */
    private static function castBackedEnums(array $properties): array
    {
        $definition = static::definition();
        $cast = [];

        foreach ($properties as $property) {
            $value = $property->value;
            $types = $definition->property($property->key)->types ?? [];

            if ($value instanceof BackedEnum && is_string($value->value)
                && array_filter($types, static fn (Type $type): bool => $type->accepts($value)) === []
                && array_filter($types, static fn (Type $type): bool => $type->kind === TypeKind::String) !== []) {
                $property = new Property($property->key, $value->value);
            }

            $cast[] = $property;
        }

        return $cast;
    }

    /**
     * @param  list<Property>  $properties
     */
    private static function duplicateKey(array $properties): string
    {
        $seen = [];

        foreach ($properties as $property) {
            if (isset($seen[$property->key])) {
                return $property->key;
            }

            $seen[$property->key] = true;
        }

        return '';
    }
}
