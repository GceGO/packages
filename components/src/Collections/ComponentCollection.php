<?php

declare(strict_types=1);

namespace Gcego\Components\Collections;

use ArrayIterator;
use Closure;
use Countable;
use Gcego\Components\Contracts\ComponentInterface;
use IteratorAggregate;
use Traversable;

/**
 * An immutable, ordered list of child components.
 *
 * @implements IteratorAggregate<int, ComponentInterface>
 */
final readonly class ComponentCollection implements Countable, IteratorAggregate
{
    /**
     * @var list<ComponentInterface>
     */
    private array $components;

    public function __construct(ComponentInterface ...$components)
    {
        $this->components = array_values($components);
    }

    public function get(int $index): ?ComponentInterface
    {
        return $this->components[$index] ?? null;
    }

    public function has(int $index): bool
    {
        return isset($this->components[$index]);
    }

    public function first(): ?ComponentInterface
    {
        return $this->components[0] ?? null;
    }

    /**
     * @return list<ComponentInterface>
     */
    public function all(): array
    {
        return $this->components;
    }

    /**
     * @param  Closure(ComponentInterface, int): bool  $predicate
     */
    public function filter(Closure $predicate): self
    {
        $matches = [];

        foreach ($this->components as $index => $component) {
            if ($predicate($component, $index)) {
                $matches[] = $component;
            }
        }

        return new self(...$matches);
    }

    /**
     * Components whose type identifier equals $type or that are instances of the class $type.
     */
    public function ofType(string $type): self
    {
        return $this->filter(static fn (ComponentInterface $component): bool => $component->type() === $type || $component instanceof $type);
    }

    /**
     * @return list<ComponentInterface>
     */
    public function toArray(): array
    {
        return $this->components;
    }

    public function isEmpty(): bool
    {
        return $this->components === [];
    }

    public function count(): int
    {
        return count($this->components);
    }

    /**
     * @return Traversable<int, ComponentInterface>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->components);
    }
}
