<?php

declare(strict_types=1);

namespace Gcego\Components\Building;

use Gcego\Components\Component;
use Gcego\Components\Contracts\Buildable;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Exceptions\InvalidBuilderException;
use Gcego\Components\Property;
use Gcego\Components\TypeKind;

/**
 * The fluent, definition-time form of a component:
 *
 *     Text::define('name', 'Subject Name')->searchable()->sortable()->width('sm')
 *
 * Leading positional values follow the component's shorthand, named values
 * set properties (define('active', 'Active', field: 'is_active')), and every
 * unknown method sets the property its name maps to: sortable() sets
 * Text::Sortable, maxLength(100) sets TextInput::MaxLength. A method called
 * without a value turns an on/off property on.
 *
 * Builders are immutable (every call returns a copy) and are built into the
 * real component when they are placed in a parent, returned from a
 * definition file, or when build() is called. Components with richer
 * configuration ship their own builder (see Component::builderClass()).
 */
class ComponentBuilder implements Buildable
{
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
        $copy->properties[$key] = new Property($key, $value);

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
     * @param  iterable<ComponentInterface|Buildable|null>|ComponentInterface|Buildable  $children
     */
    public function schema(iterable|ComponentInterface|Buildable $children): static
    {
        return $this->with(...(is_iterable($children) ? [...$children] : [$children]));
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
        array_push($copy->configurations, ...$configurations);

        return $copy;
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
     */
    public function when(bool $condition, callable $callback): static
    {
        return $condition ? $callback($this) : $this;
    }

    public function build(): Component
    {
        $component = $this->component;

        return new $component(
            ...array_values($this->properties),
            ...array_map(static fn (ComponentInterface|Buildable $child): ComponentInterface => $child instanceof Buildable ? $child->build() : $child, $this->children),
            ...$this->configurations,
        );
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

        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $method));

        return $definition->property($snake) !== null ? $snake : null;
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
                $this->properties[$key] = new Property($key, $item);
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
                $this->properties[$shorthand[$position]] = new Property($shorthand[$position], $item);
            }

            $position++;
        }

        $this->absorbItems($rest);
    }

    /**
     * @param  array<int|string, mixed>  $items
     */
    private function absorbItems(array $items): void
    {
        foreach ($items as $item) {
            if ($item === null) {
                continue;
            }

            if (is_iterable($item)) {
                $this->absorbItems([...$item]);
            } elseif ($item instanceof Property) {
                $this->properties[$item->key] = $item;
            } elseif ($item instanceof ComponentInterface || $item instanceof Buildable) {
                $this->children[] = $item;
            } elseif ($item instanceof ConfigurationNode) {
                $this->configurations[] = $item;
            } else {
                throw InvalidBuilderException::unsupportedItem($this->component, $item);
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
