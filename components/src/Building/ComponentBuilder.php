<?php

declare(strict_types=1);

namespace Gcego\Components\Building;

use Closure;
use Gcego\Components\Component;
use Gcego\Components\Contracts\Buildable;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Evaluation\Computed;
use Gcego\Components\Exceptions\InvalidBuilderException;
use Gcego\Components\Facades\Components;
use Gcego\Components\Inspection\ComponentInspection;
use Gcego\Components\Properties\Property;
use Gcego\Components\Schema\TypeKind;
use Gcego\Components\Trees\ComponentTree;
use Gcego\Components\Validation\ValidationResult;

/**
 * The fluent, definition-time form of a component:
 *
 *     Text::define('name', 'Subject Name')->searchable()->sortable()->width('sm')
 *
 * Leading positional values follow the component's shorthand, named values
 * set properties (define('active', 'Active', field: 'is_active')), and every
 * unknown method sets the property its name maps to: sortable() sets
 * Text::Sortable, maxLength(100) sets TextInput::MaxLength. A method called
 * without a value turns an on/off property on. Closures become Computed
 * values (visible(fn (Get $get): bool => ...)), and registered macros are
 * resolved before property setters. Catalog components start builders
 * Filament-style with make().
 *
 * Builders are immutable: changes return copies and conditional no-ops may
 * return the original builder. They are built into the real component when they are placed in a parent, returned from a
 * definition file, or when build() is called. Components with richer
 * configuration ship their own builder (see Component::builderClass()).
 */
class ComponentBuilder implements Buildable
{
    private const int MaxItemDepth = 32;

    /**
     * @var array<string, Property>
     */
    private array $properties = [];

    /**
     * @var list<ComponentInterface|Buildable>
     */
    private array $children = [];

    /**
     * @var list<ConfigurationNode>
     */
    private array $configurations = [];

    /**
     * @param  class-string<Component>  $component
     * @param  array<int|string, mixed>  $items
     */
    final public function __construct(protected readonly string $component, array $items = [])
    {
        Components::getFacadeRoot();

        if (! is_subclass_of($component, Component::class) && $component !== Component::class) {
            throw InvalidBuilderException::notAComponent($component);
        }

        $this->absorb($this->prepare($items));
    }

    /**
     * The component class this builder produces.
     *
     * @return class-string<Component>
     */
    final public function componentClass(): string
    {
        return $this->component;
    }

    /**
     * Set the property a method name maps to: searchable() -> Searchable.
     *
     * @param  array<int|string, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): static
    {
        $macro = Components::configurator()->macroFor($this->component, $method);

        if ($macro !== null) {
            return $this->callMacro($method, $macro, $arguments);
        }

        $key = $this->propertyKey($method);

        if ($key === null) {
            throw InvalidBuilderException::unknownMethod($this->component, $method);
        }

        if (count($arguments) > 1 || array_filter(array_keys($arguments), is_string(...)) !== []) {
            throw InvalidBuilderException::tooManyValues($this->component, $method);
        }

        if ($arguments === []) {
            if (! $this->isSwitch($key)) {
                throw InvalidBuilderException::missingValue($this->component, $method);
            }

            return $this->set($key, true);
        }

        return $this->set($key, $arguments[array_key_first($arguments)]);
    }

    /**
     * Add or replace one property.
     */
    public function set(string $key, mixed $value): static
    {
        $copy = clone $this;
        $copy->properties[$key] = self::property($key, $value);

        return $copy;
    }

    /**
     * Merge explicit properties; later duplicate keys replace earlier values.
     *
     * @param  iterable<string, mixed>  $properties
     */
    public function setMany(iterable $properties): static
    {
        $copy = clone $this;

        foreach ($properties as $key => $value) {
            if (! is_string($key)) {
                throw InvalidBuilderException::invalidValue($this->component, 'setMany', 'Property keys must be strings.');
            }

            $copy->properties[$key] = self::property($key, $value);
        }

        return $copy;
    }

    /** Explicit null counts as a value; schema defaults do not count as explicit properties. */
    public function setIfMissing(string $key, mixed $value): static
    {
        return $this->has($key) ? $this : $this->set($key, $value);
    }

    /** Read an explicit value without building children or resolving schema defaults. */
    public function get(string $key, mixed $fallback = null): mixed
    {
        return $this->propertyValue($key, $fallback);
    }

    public function has(string $key): bool
    {
        return $this->hasPropertyValue($key);
    }

    /** @return array<string, mixed> */
    public function properties(): array
    {
        $values = [];

        foreach ($this->properties as $key => $property) {
            $values[$key] = $property->value;
        }

        return $values;
    }

    public function clearProperties(): static
    {
        $copy = clone $this;
        $copy->properties = [];

        return $copy;
    }

    /** @param iterable<string, mixed> $properties */
    public function replaceProperties(iterable $properties): static
    {
        return $this->clearProperties()->setMany($properties);
    }

    public function onlyProperties(string ...$keys): static
    {
        $copy = clone $this;
        $copy->properties = array_intersect_key($this->properties, array_fill_keys($keys, true));

        return $copy;
    }

    public function forget(string ...$keys): static
    {
        $copy = clone $this;

        foreach ($keys as $key) {
            unset($copy->properties[$key]);
        }

        return $copy;
    }

    /**
     * Append children: components or their builders (nulls are skipped, so
     * children can be conditional).
     *
     * @param  iterable<ComponentInterface|Buildable|iterable<mixed>|null>|ComponentInterface|Buildable  $children
     */
    public function schema(iterable|ComponentInterface|Buildable $children): static
    {
        $copy = clone $this;
        $copy->absorbItems([$children], childrenOnly: true);

        return $copy;
    }

    /**
     * @param  iterable<ComponentInterface|Buildable|iterable<mixed>|null>|ComponentInterface|Buildable  $children
     */
    public function components(iterable|ComponentInterface|Buildable $children): static
    {
        return $this->schema($children);
    }

    /**
     * @param  iterable<ComponentInterface|Buildable|iterable<mixed>|null>|ComponentInterface|Buildable  $children
     */
    public function child(iterable|ComponentInterface|Buildable $children): static
    {
        return $this->schema($children);
    }

    /** @param iterable<ComponentInterface|Buildable|iterable<mixed>|null>|ComponentInterface|Buildable $children */
    public function replaceChildren(iterable|ComponentInterface|Buildable $children): static
    {
        return $this->clearChildren()->schema($children);
    }

    /** @param iterable<ComponentInterface|Buildable|iterable<mixed>|null>|ComponentInterface|Buildable $children */
    public function prependChildren(iterable|ComponentInterface|Buildable $children): static
    {
        $copy = $this->clearChildren()->schema($children);
        array_push($copy->children, ...$this->children);

        return $copy;
    }

    public function clearChildren(): static
    {
        $copy = clone $this;
        $copy->children = [];

        return $copy;
    }

    /**
     * Child builders remain unbuilt until build() is called.
     *
     * @return list<ComponentInterface|Buildable>
     */
    public function children(): array
    {
        return $this->children;
    }

    public function childCount(): int
    {
        return count($this->children);
    }

    /** @param callable(ComponentInterface|Buildable, int): bool $predicate */
    public function filterChildren(callable $predicate): static
    {
        $children = [];

        foreach ($this->children as $index => $child) {
            if ($predicate($child, $index)) {
                $children[] = $child;
            }
        }

        return $this->withChildItems($children);
    }

    /** @param callable(ComponentInterface|Buildable, int): (ComponentInterface|Buildable|null) $callback */
    public function mapChildren(callable $callback): static
    {
        $copy = $this->clearChildren();

        foreach ($this->children as $index => $child) {
            $mapped = $callback($child, $index);

            if ($mapped !== null && ! $mapped instanceof ComponentInterface && ! $mapped instanceof Buildable) {
                throw InvalidBuilderException::invalidChild($this->component, $mapped);
            }

            if ($mapped !== null) {
                $copy->children[] = $mapped;
            }
        }

        return $copy;
    }

    public function label(mixed $label): static
    {
        return $this->__call('label', [$label]);
    }

    /**
     * Span every column of the parent grid.
     */
    public function columnSpanFull(): static
    {
        return $this->__call('columnSpan', ['full']);
    }

    /**
     * Hide the component during the given operations (create, edit, view, ...).
     *
     * @param  string|list<string>  ...$operations
     */
    public function hiddenOn(string|array ...$operations): static
    {
        return $this->__call('hiddenOn', [self::operations($operations)]);
    }

    /**
     * Show the component only during the given operations.
     *
     * @param  string|list<string>  ...$operations
     */
    public function visibleOn(string|array ...$operations): static
    {
        return $this->__call('visibleOn', [self::operations($operations)]);
    }

    /**
     * Set extra HTML attributes, or merge them into the ones already set.
     *
     * @param  array<string, scalar|null>|Closure  $attributes
     */
    public function extraAttributes(array|Closure $attributes, bool $merge = false): static
    {
        $current = $this->propertyValue('extraAttributes');

        if ($merge && is_array($attributes) && is_array($current)) {
            $attributes = [...$current, ...$attributes];
        }

        return $this->__call('extraAttributes', [$attributes]);
    }

    /**
     * Append any items a constructor would accept: properties, children,
     * builders and configuration nodes (lists are flattened, nulls skipped).
     */
    public function with(mixed ...$items): static
    {
        $copy = clone $this;
        $copy->absorbItems($items);

        return $copy;
    }

    /**
     * Append configuration nodes.
     */
    public function configure(ConfigurationNode ...$configurations): static
    {
        $copy = clone $this;

        foreach ($configurations as $configuration) {
            $copy->configurations[] = $configuration;
        }

        return $copy;
    }

    public function replaceConfigurations(ConfigurationNode ...$configurations): static
    {
        $copy = clone $this;
        $copy->configurations = array_values($configurations);

        return $copy;
    }

    public function clearConfigurations(): static
    {
        return $this->replaceConfigurations();
    }

    /**
     * Apply a callback to this builder and return its result, for reusable
     * or conditional definition fragments.
     *
     * @param  callable(static): static  $callback
     */
    public function tap(callable $callback): static
    {
        return $callback($this);
    }

    /**
     * Apply the callback only when the condition holds.
     *
     * @param  callable(static): static  $callback
     * @param  (callable(static): static)|null  $otherwise
     */
    public function when(bool $condition, callable $callback, ?callable $otherwise = null): static
    {
        if ($condition) {
            return $callback($this);
        }

        return $otherwise === null ? $this : $otherwise($this);
    }

    /**
     * @param  callable(static): static  $callback
     * @param  (callable(static): static)|null  $otherwise
     */
    public function unless(bool $condition, callable $callback, ?callable $otherwise = null): static
    {
        return $this->when(! $condition, $callback, $otherwise);
    }

    /**
     * Compose reusable immutable builder transformations in order.
     *
     * @param  iterable<callable(static): static>  $callbacks
     */
    public function apply(iterable $callbacks): static
    {
        $builder = $this;

        foreach ($callbacks as $callback) {
            $builder = $builder->tap($callback);
        }

        return $builder;
    }

    public function buildValidated(): Component
    {
        return $this->build()->assertValid();
    }

    public function validate(): ValidationResult
    {
        return $this->build()->validate();
    }

    /** @return array<string, mixed> */
    public function normalize(): array
    {
        return $this->build()->normalize();
    }

    public function tree(): ComponentTree
    {
        return $this->build()->tree();
    }

    public function inspect(): ComponentInspection
    {
        return Components::inspect($this->build());
    }

    public function build(): Component
    {
        $component = $this->instantiate($this->buildItems());

        if (! $component instanceof $this->component) {
            throw InvalidBuilderException::wrongComponent($this->component, $component::class);
        }

        return $component;
    }

    /**
     * Adapt canonical items to a component with a custom constructor.
     * Override this hook rather than bypassing the component's constructor.
     *
     * @param  list<Property|ComponentInterface|ConfigurationNode>  $items
     */
    protected function instantiate(array $items): Component
    {
        $component = $this->component;

        return new $component(...$items);
    }

    /**
     * @return list<Property|ComponentInterface|ConfigurationNode>
     */
    protected function buildItems(): array
    {
        return [
            ...array_values($this->properties),
            ...array_map(static fn (ComponentInterface|Buildable $child): ComponentInterface => $child instanceof Buildable ? $child->build() : $child, $this->children),
            ...$this->configurations,
        ];
    }

    /**
     * Normalize the define() arguments before they are read, for components
     * whose positional arguments read differently from their constructor.
     *
     * @param  array<int|string, mixed>  $items
     * @return array<int|string, mixed>
     */
    protected function prepare(array $items): array
    {
        return $items;
    }

    /**
     * The value currently set for a property.
     */
    protected function propertyValue(string $key, mixed $fallback = null): mixed
    {
        return isset($this->properties[$key]) ? $this->properties[$key]->value : $fallback;
    }

    protected function hasPropertyValue(string $key): bool
    {
        return isset($this->properties[$key]);
    }

    /**
     * Copy without the configuration nodes of the given class, so a fluent
     * method can replace (rather than repeat) its node.
     *
     * @param  class-string<ConfigurationNode>  $class
     */
    protected function withoutConfiguration(string $class): static
    {
        $copy = clone $this;
        $copy->configurations = array_values(array_filter($this->configurations, static fn (ConfigurationNode $node): bool => ! $node instanceof $class));

        return $copy;
    }

    /**
     * Copy with the node replacing any earlier node of its class.
     */
    protected function replaceConfiguration(ConfigurationNode $configuration): static
    {
        return $this->withoutConfiguration($configuration::class)->configure($configuration);
    }

    /**
     * The configuration nodes declared so far, optionally of one class.
     *
     * @template T of ConfigurationNode
     *
     * @param  class-string<T>|null  $class
     * @return list<($class is null ? ConfigurationNode : T)>
     */
    protected function configurationsOf(?string $class = null): array
    {
        return $class === null ? $this->configurations : array_values(array_filter($this->configurations, static fn (ConfigurationNode $node): bool => $node instanceof $class));
    }

    /**
     * Copy with every child produced by the given component class replaced by
     * the given child (kept at the first replaced position, else appended).
     *
     * @param  class-string<ComponentInterface>  $class
     */
    protected function replaceChild(string $class, ComponentInterface|Buildable $replacement): static
    {
        $copy = clone $this;
        $children = [];
        $placed = false;

        foreach ($this->children as $child) {
            if (! self::produces($child, $class)) {
                $children[] = $child;
            } elseif (! $placed) {
                $children[] = $replacement;
                $placed = true;
            }
        }

        if (! $placed) {
            $children[] = $replacement;
        }

        $copy->children = $children;

        return $copy;
    }

    /**
     * The first child produced by the given component class.
     *
     * @param  class-string<ComponentInterface>  $class
     */
    protected function childOf(string $class): ComponentInterface|Buildable|null
    {
        foreach ($this->children as $child) {
            if (self::produces($child, $class)) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return list<ComponentInterface|Buildable>
     */
    protected function childItems(): array
    {
        return $this->children;
    }

    /**
     * Copy with the children replaced.
     *
     * @param  list<ComponentInterface|Buildable>  $children
     */
    protected function withChildItems(array $children): static
    {
        $copy = clone $this;
        $copy->children = $children;

        return $copy;
    }

    /**
     * Whether a child is, or builds, an instance of the class.
     *
     * @param  class-string  $class
     */
    protected static function produces(ComponentInterface|Buildable $child, string $class): bool
    {
        if ($child instanceof self) {
            return is_a($child->component, $class, true);
        }

        return $child instanceof $class;
    }

    /**
     * The property key a method name maps to, if the component defines it.
     */
    protected function propertyKey(string $method): ?string
    {
        $definition = $this->component::definition();
        $constant = $this->component.'::'.ucfirst($method);

        if (defined($constant)) {
            $key = constant($constant);

            if (is_string($key) && $definition->property($key) !== null) {
                return $key;
            }
        }

        if ($definition->property($method) !== null) {
            return $method;
        }

        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $method));

        return $definition->property($snake) !== null ? $snake : null;
    }

    /**
     * Closures become Computed values, evaluated by Component::evaluateProperty().
     */
    private static function property(string $key, mixed $value): Property
    {
        return new Property($key, $value instanceof Closure ? new Computed($value) : $value);
    }

    /**
     * @param  array<int|string, mixed>  $arguments
     */
    private function callMacro(string $method, Closure $macro, array $arguments): static
    {
        $result = Closure::bind($macro, $this, static::class)(...$arguments);

        if (! $result instanceof static || $result->component !== $this->component) {
            throw InvalidBuilderException::invalidValue(
                $this->component,
                $method,
                'Macros must return the builder, because builders are immutable: return $this->required();',
            );
        }

        return $result;
    }

    /**
     * @param  array<array-key, string|list<string>>  $operations
     * @return list<string>
     */
    private static function operations(array $operations): array
    {
        $flattened = [];

        foreach ($operations as $operation) {
            array_push($flattened, ...(is_array($operation) ? array_values($operation) : [$operation]));
        }

        return $flattened;
    }

    private function isSwitch(string $key): bool
    {
        foreach ($this->component::definition()->property($key)->types ?? [] as $type) {
            if ($type->kind === TypeKind::Bool || $type->kind === TypeKind::Mixed) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int|string, mixed>  $items
     */
    private function absorb(array $items): void
    {
        $shorthand = $this->component::shorthandKeys();
        $position = 0;
        $positional = true;
        $rest = [];

        foreach ($items as $name => $item) {
            if (is_string($name)) {
                $key = $this->propertyKey($name) ?? throw InvalidBuilderException::unknownMethod($this->component, $name);
                $this->properties[$key] = self::property($key, $item);
                $positional = false;

                continue;
            }

            if (self::isItem($item)) {
                $rest[] = $item;
                $positional = false;

                continue;
            }

            if (! $positional) {
                throw InvalidBuilderException::positionalAfterItem($this->component, $item);
            }

            if (! array_key_exists($position, $shorthand)) {
                throw InvalidBuilderException::tooManyPositional($this->component, $item);
            }

            if ($item !== null) {
                $this->properties[$shorthand[$position]] = self::property($shorthand[$position], $item);
            }

            $position++;
        }

        $this->absorbItems($rest);
    }

    /**
     * Consume nested lists once, checking node contracts before iterable expansion.
     * Child-only entry points use the same depth and identity rules as mixed items.
     *
     * @param  iterable<mixed>  $items
     */
    private function absorbItems(iterable $items, int $depth = 0, bool $childrenOnly = false): void
    {
        if ($depth > self::MaxItemDepth) {
            throw InvalidBuilderException::nestingTooDeep($this->component, self::MaxItemDepth);
        }

        foreach ($items as $item) {
            if ($item === null) {
                continue;
            }

            if ($item instanceof ComponentInterface || $item instanceof Buildable) {
                $this->children[] = $item;
            } elseif (! $childrenOnly && $item instanceof Property) {
                $this->properties[$item->key] = $item;
            } elseif (! $childrenOnly && $item instanceof ConfigurationNode) {
                $this->configurations[] = $item;
            } elseif (! self::isItem($item) && is_iterable($item)) {
                $this->absorbItems($item, $depth + 1, $childrenOnly);
            } else {
                throw $childrenOnly
                    ? InvalidBuilderException::invalidChild($this->component, $item)
                    : InvalidBuilderException::unsupportedItem($this->component, $item);
            }
        }
    }

    private static function isItem(mixed $item): bool
    {
        return $item instanceof Property
            || $item instanceof ComponentInterface
            || $item instanceof Buildable
            || $item instanceof ConfigurationNode;
    }
}
