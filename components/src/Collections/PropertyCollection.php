<?php

declare(strict_types=1);

namespace Gcego\Components\Collections;

use ArrayIterator;
use Countable;
use Gcego\Components\Exceptions\DuplicatePropertyException;
use Gcego\Components\Properties\Property;
use IteratorAggregate;
use Traversable;

/**
 * An immutable, ordered set of properties keyed by property key.
 *
 * @implements IteratorAggregate<string, Property>
 */
final readonly class PropertyCollection implements Countable, IteratorAggregate
{
    /**
     * @var array<string, Property>
     */
    private array $properties;

    /**
     * @throws DuplicatePropertyException when two properties share a key.
     */
    public function __construct(Property ...$properties)
    {
        $indexed = [];

        foreach ($properties as $property) {
            if (isset($indexed[$property->key])) {
                throw DuplicatePropertyException::forKey($property->key);
            }

            $indexed[$property->key] = $property;
        }

        $this->properties = $indexed;
    }

    public function has(string $key): bool
    {
        return isset($this->properties[$key]);
    }

    public function get(string $key): ?Property
    {
        return $this->properties[$key] ?? null;
    }

    public function value(string $key, mixed $fallback = null): mixed
    {
        return isset($this->properties[$key]) ? $this->properties[$key]->value : $fallback;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->properties);
    }

    /**
     * @return array<string, Property>
     */
    public function all(): array
    {
        return $this->properties;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_map(static fn (Property $property): mixed => $property->value, $this->properties);
    }

    /**
     * Return a copy where the given properties replace any existing ones with the same key.
     */
    public function with(Property ...$properties): self
    {
        $merged = $this->properties;

        foreach ($properties as $property) {
            $merged[$property->key] = $property;
        }

        return new self(...array_values($merged));
    }

    public function without(string ...$keys): self
    {
        return new self(...array_values(array_diff_key($this->properties, array_flip($keys))));
    }

    public function isEmpty(): bool
    {
        return $this->properties === [];
    }

    public function count(): int
    {
        return count($this->properties);
    }

    /**
     * @return Traversable<string, Property>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->properties);
    }
}
