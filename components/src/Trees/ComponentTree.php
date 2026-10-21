<?php

declare(strict_types=1);

namespace Gcego\Components\Trees;

use ArrayIterator;
use Countable;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Facades\Components;
use Gcego\Components\Traversal\ComponentWalker;
use IteratorAggregate;
use Traversable;

/**
 * A root component and all of its descendant occurrences.
 *
 * The node index is built lazily on first use, once, with an iterative walk.
 * Iteration order is depth-first pre-order (document order).
 *
 * @implements IteratorAggregate<int, TreeNode>
 */
final class ComponentTree implements Countable, IteratorAggregate
{
    /**
     * @var list<TreeNode>|null
     */
    private ?array $nodes = null;

    /**
     * @var array<string, TreeNode>
     */
    private array $nodesByPath = [];

    public function __construct(
        private readonly ComponentInterface $root,
        private readonly ComponentWalker $walker,
    ) {
        Components::getFacadeRoot();
    }

    public function root(): ComponentInterface
    {
        return $this->root;
    }

    public function rootNode(): TreeNode
    {
        return $this->nodes()[0];
    }

    /**
     * @return list<TreeNode>
     */
    public function depthFirst(): array
    {
        return $this->nodes();
    }

    /**
     * @return list<TreeNode>
     */
    public function breadthFirst(): array
    {
        return iterator_to_array($this->walker->breadthFirst($this->root), false);
    }

    public function find(string|ComponentPath $path): ?TreeNode
    {
        $this->nodes();

        return $this->nodesByPath[(string) $path] ?? null;
    }

    public function contains(string|ComponentPath $path): bool
    {
        return $this->find($path) !== null;
    }

    /**
     * The first occurrence, in document order, of a component with the explicit id.
     */
    public function findById(string $id): ?TreeNode
    {
        foreach ($this->nodes() as $node) {
            if ($node->component->id() === $id) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Nodes whose type identifier equals $type or that are instances of the class $type.
     *
     * @return list<TreeNode>
     */
    public function findByType(string $type): array
    {
        return array_values(array_filter(
            $this->nodes(),
            static fn (TreeNode $node): bool => $node->component->type() === $type || $node->component instanceof $type,
        ));
    }

    public function parentOf(string|ComponentPath|TreeNode $node): ?TreeNode
    {
        $parentPath = $this->resolve($node)?->parentPath;

        return $parentPath === null ? null : $this->find($parentPath);
    }

    /**
     * @return list<TreeNode>
     */
    public function childrenOf(string|ComponentPath|TreeNode $node): array
    {
        $resolved = $this->resolve($node);

        return $resolved === null ? [] : $this->walker->childNodes($resolved);
    }

    /**
     * Number of descendants below the given node (the root by default).
     */
    public function descendantCount(string|ComponentPath|TreeNode|null $node = null): int
    {
        $resolved = $node === null ? $this->rootNode() : $this->resolve($node);

        if ($resolved === null) {
            return 0;
        }

        $count = 0;

        foreach ($this->nodes() as $candidate) {
            if ($resolved->path->isAncestorOf($candidate->path)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * The greatest node depth; a lone root has depth 0.
     */
    public function depth(): int
    {
        $depth = 0;

        foreach ($this->nodes() as $node) {
            $depth = max($depth, $node->depth);
        }

        return $depth;
    }

    /**
     * Explicit ids used by more than one occurrence, with the paths that use them.
     *
     * @return array<string, list<string>>
     */
    public function duplicateIds(): array
    {
        $paths = [];

        foreach ($this->nodes() as $node) {
            $id = $node->component->id();

            if ($id !== null) {
                $paths[$id][] = $node->path->toString();
            }
        }

        return array_filter($paths, static fn (array $occurrences): bool => count($occurrences) > 1);
    }

    /**
     * Total number of nodes, including the root.
     */
    public function count(): int
    {
        return count($this->nodes());
    }

    /**
     * @return Traversable<int, TreeNode>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->nodes());
    }

    /**
     * @return non-empty-list<TreeNode>
     */
    private function nodes(): array
    {
        if ($this->nodes === null) {
            $nodes = [];
            $nodesByPath = [];

            foreach ($this->walker->preOrder($this->root) as $node) {
                $nodes[] = $node;
                $nodesByPath[$node->path->toString()] = $node;
            }

            $this->nodesByPath = $nodesByPath;
            $this->nodes = $nodes;
        }

        /** @var non-empty-list<TreeNode> */
        return $this->nodes;
    }

    private function resolve(string|ComponentPath|TreeNode $node): ?TreeNode
    {
        if (! $node instanceof TreeNode) {
            return $this->find($node);
        }

        $resolved = $this->find($node->path);

        return $resolved !== null && $resolved->component === $node->component ? $resolved : null;
    }
}
