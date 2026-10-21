<?php

declare(strict_types=1);

namespace Gcego\Components\Trees;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Support\ComponentIdentity;

/**
 * One occurrence of a component inside a tree.
 *
 * The same immutable component instance may occur at several positions; each
 * occurrence gets its own TreeNode with its own path. Components never hold
 * parent pointers, so reuse cannot corrupt shared state.
 */
final readonly class TreeNode
{
    public function __construct(
        public ComponentInterface $component,
        public ComponentPath $path,
        public int $depth,
        public int $index,
        public ?ComponentPath $parentPath,
    ) {}

    public function isRoot(): bool
    {
        return $this->parentPath === null;
    }

    public function identity(): ComponentIdentity
    {
        return new ComponentIdentity($this->component->type(), $this->component->id(), $this->path);
    }
}
