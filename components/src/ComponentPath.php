<?php

declare(strict_types=1);

namespace Gcego\Components;

use Gcego\Components\Exceptions\InvalidConfigurationException;
use Stringable;

/**
 * The deterministic location of a component occurrence in a tree.
 *
 * Each segment is the component's name when it is a simple identifier
 * ([A-Za-z0-9_-]+) and unique among its siblings, "name#index" when an
 * earlier sibling already uses that name, and "#index" otherwise. Segments
 * are joined with ".", e.g. "subjects.table.#2".
 *
 * Paths are immutable and share their parent path, so every node of a deep
 * tree costs constant memory.
 */
final class ComponentPath implements Stringable
{
    public const string Separator = '.';

    private ?string $string = null;

    private function __construct(
        private readonly ?self $parentPath,
        private readonly string $segment,
        private readonly int $depth,
    ) {}

    public static function root(string $segment): self
    {
        return new self(null, $segment, 0);
    }

    /**
     * @param  list<string>  $segments
     */
    public static function fromSegments(array $segments): self
    {
        if ($segments === []) {
            throw InvalidConfigurationException::invalidDefinition('A component path needs at least one segment.');
        }

        $path = self::root(array_shift($segments));

        foreach ($segments as $segment) {
            $path = $path->child($segment);
        }

        return $path;
    }

    public static function fromString(string $path): self
    {
        return self::fromSegments(explode(self::Separator, $path));
    }

    public function child(string $segment): self
    {
        return new self($this, $segment, $this->depth + 1);
    }

    public function parent(): ?self
    {
        return $this->parentPath;
    }

    /**
     * @return list<string>
     */
    public function segments(): array
    {
        $segments = [];

        for ($path = $this; $path !== null; $path = $path->parentPath) {
            $segments[] = $path->segment;
        }

        return array_reverse($segments);
    }

    public function last(): string
    {
        return $this->segment;
    }

    /**
     * The root path has depth 0.
     */
    public function depth(): int
    {
        return $this->depth;
    }

    public function isAncestorOf(self $other): bool
    {
        if ($other->depth <= $this->depth) {
            return false;
        }

        $candidate = $other;

        while ($candidate !== null && $candidate->depth > $this->depth) {
            $candidate = $candidate->parentPath;
        }

        return $candidate !== null && $candidate->equals($this);
    }

    public function equals(self $other): bool
    {
        return $this === $other || ($this->depth === $other->depth && $this->toString() === $other->toString());
    }

    public function toString(): string
    {
        return $this->string ??= $this->parentPath === null
            ? $this->segment
            : $this->parentPath->toString().self::Separator.$this->segment;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
