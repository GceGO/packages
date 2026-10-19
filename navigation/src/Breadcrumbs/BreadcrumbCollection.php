<?php

declare(strict_types=1);

namespace Gcego\Navigation\Breadcrumbs;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Breadcrumbs in order; the last one is the current page.
 *
 * @implements IteratorAggregate<int, Breadcrumb>
 */
final readonly class BreadcrumbCollection implements Countable, IteratorAggregate
{
    /**
     * @var list<Breadcrumb>
     */
    public array $items;

    public function __construct(Breadcrumb ...$items)
    {
        $items = array_values($items);
        $last = count($items) - 1;
        $this->items = array_map(static fn (Breadcrumb $crumb, int $index): Breadcrumb => $crumb->asCurrent($index === $last), $items, array_keys($items));
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function current(): ?Breadcrumb
    {
        return $this->items === [] ? null : $this->items[array_key_last($this->items)];
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return Traversable<int, Breadcrumb>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return list<array{label: string, url: string|null, current: bool}>
     */
    public function toArray(): array
    {
        return array_map(static fn (Breadcrumb $crumb): array => $crumb->toArray(), $this->items);
    }
}
