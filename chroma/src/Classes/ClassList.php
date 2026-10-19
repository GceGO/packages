<?php

declare(strict_types=1);

namespace Gcego\Chroma\Classes;

use ArrayIterator;
use Countable;
use Gcego\Chroma\Exceptions\InvalidClassException;
use IteratorAggregate;
use Stringable;
use Traversable;

/**
 * An immutable, normalized, ordered list of class tokens.
 *
 * @implements IteratorAggregate<int, string>
 */
final readonly class ClassList implements Countable, IteratorAggregate, Stringable
{
    /**
     * @param  list<string>  $classes  Already normalized (use from()).
     */
    private function __construct(private array $classes) {}

    /**
     * @param  string|iterable<mixed>|self  $classes
     *
     * @throws InvalidClassException
     */
    public static function from(string|iterable|self $classes, ClassNormalizer $normalizer = new ClassNormalizer): self
    {
        return $classes instanceof self ? $classes : new self($normalizer->normalize($classes));
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function merge(self $overrides, ClassMerger $merger = new ClassMerger): self
    {
        return new self($merger->merge($this->classes, $overrides->classes));
    }

    public function replace(self $classes): self
    {
        return $classes;
    }

    /**
     * Remove exact classes.
     */
    public function without(self $classes): self
    {
        return new self(array_values(array_diff($this->classes, $classes->classes)));
    }

    public function contains(string $class): bool
    {
        return in_array($class, $this->classes, true);
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return $this->classes;
    }

    public function isEmpty(): bool
    {
        return $this->classes === [];
    }

    public function count(): int
    {
        return count($this->classes);
    }

    public function toString(): string
    {
        return implode(' ', $this->classes);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @return Traversable<int, string>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->classes);
    }
}
