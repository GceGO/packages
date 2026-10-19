<?php

declare(strict_types=1);

namespace Gcego\Navigation\Tree;

use Gcego\Navigation\NavigationNode;
use Gcego\Security\Contracts\Requirement;

/**
 * One normalized node of a navigation tree: request-independent structure
 * only (identity, type, order, own requirements, sorted children).
 */
final readonly class Node
{
    /**
     * @param  list<Requirement>  $requirements  The node's own requirements.
     * @param  list<Node>  $children  Sorted.
     */
    public function __construct(
        public string $id,
        public string $path,
        public string $type,
        public NavigationNode $component,
        public array $requirements,
        public int $order,
        public int $position,
        public int $depth,
        public array $children = [],
    ) {}

    /**
     * @param  list<Node>  $children
     */
    public function withChildren(array $children): self
    {
        return new self($this->id, $this->path, $this->type, $this->component, $this->requirements, $this->order, $this->position, $this->depth, NavigationTreeBuilder::sort($children));
    }

    public function isContainer(): bool
    {
        return $this->type === 'group' || $this->type === 'dropdown';
    }
}
