<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Composition;

use ArrayIterator;
use Countable;
use Gcego\Blueprints\Contracts\BlueprintPart;
use IteratorAggregate;
use Traversable;

/**
 * An immutable, ordered list of classified parts. Iterates the parts themselves.
 *
 * @implements IteratorAggregate<int, BlueprintPart>
 */
final readonly class BlueprintPartCollection implements Countable, IteratorAggregate
{
    /**
     * @var list<ClassifiedPart>
     */
    private array $entries;

    public function __construct(ClassifiedPart ...$entries)
    {
        $this->entries = array_values($entries);
    }

    /**
     * @return list<BlueprintPart>
     */
    public function all(): array
    {
        return array_map(static fn (ClassifiedPart $entry): BlueprintPart => $entry->part, $this->entries);
    }

    /**
     * @return list<ClassifiedPart>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function first(): ?BlueprintPart
    {
        return ($this->entries[0] ?? null)?->part;
    }

    public function get(int $index): ?BlueprintPart
    {
        return ($this->entries[$index] ?? null)?->part;
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        return array_map(static fn (ClassifiedPart $entry): string => $entry->path->toString(), $this->entries);
    }

    public function ofRole(string $role): self
    {
        return new self(...array_filter($this->entries, static fn (ClassifiedPart $entry): bool => $entry->role->name === $role));
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * @return Traversable<int, BlueprintPart>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->all());
    }
}
