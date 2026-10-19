<?php

declare(strict_types=1);

namespace Gcego\Components\Traversal;

use Closure;
use Gcego\Components\Component;
use Gcego\Components\ComponentPath;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Exceptions\InvalidChildException;
use Gcego\Components\TreeNode;
use Generator;
use SplQueue;

/**
 * Iterative, path-aware traversal of component graphs.
 *
 * Traversal never recurses, so deep trees cannot exhaust the call stack. A
 * component appearing among its own ancestors raises InvalidChildException,
 * and nesting deeper than $maxDepth is rejected. Reusing one instance at
 * several non-ancestral positions is allowed; each occurrence gets its own path.
 *
 * Generators are lazy: breaking out of a foreach stops traversal immediately.
 */
final readonly class ComponentWalker
{
    public const int DefaultMaxDepth = 256;

    private const string SimpleSegmentPattern = '/^[A-Za-z0-9_-]+$/';

    public function __construct(
        private int $maxDepth = self::DefaultMaxDepth,
    ) {}

    /**
     * @return Generator<int, TreeNode, Visit|null, void>
     */
    public function traverse(ComponentInterface $root, TraversalOrder $order = TraversalOrder::PreOrder): Generator
    {
        return match ($order) {
            TraversalOrder::PreOrder => $this->preOrder($root),
            TraversalOrder::PostOrder => $this->postOrder($root),
            TraversalOrder::BreadthFirst => $this->breadthFirst($root),
        };
    }

    /**
     * Send Visit::SkipChildren or Visit::Stop into the generator to steer traversal.
     *
     * @return Generator<int, TreeNode, Visit|null, void>
     */
    public function preOrder(ComponentInterface $root): Generator
    {
        /** @var list<array{TreeNode, AncestorChain|null}> $stack */
        $stack = [[$this->rootNode($root), null]];

        while ($stack !== []) {
            [$node, $ancestors] = array_pop($stack);
            $signal = yield $node;

            if ($signal === Visit::Stop) {
                return;
            }

            if ($signal === Visit::SkipChildren) {
                continue;
            }

            $childAncestors = $this->enter($node, $ancestors);

            foreach (array_reverse($this->childNodes($node)) as $child) {
                $stack[] = [$child, $childAncestors];
            }
        }
    }

    /**
     * @return Generator<int, TreeNode, Visit|null, void>
     */
    public function postOrder(ComponentInterface $root): Generator
    {
        /** @var list<array{TreeNode, AncestorChain|null, bool}> $stack */
        $stack = [[$this->rootNode($root), null, false]];

        while ($stack !== []) {
            [$node, $ancestors, $expanded] = array_pop($stack);

            if ($expanded) {
                if ((yield $node) === Visit::Stop) {
                    return;
                }

                continue;
            }

            $stack[] = [$node, $ancestors, true];
            $childAncestors = $this->enter($node, $ancestors);

            foreach (array_reverse($this->childNodes($node)) as $child) {
                $stack[] = [$child, $childAncestors, false];
            }
        }
    }

    /**
     * @return Generator<int, TreeNode, Visit|null, void>
     */
    public function breadthFirst(ComponentInterface $root): Generator
    {
        /** @var SplQueue<array{TreeNode, AncestorChain|null}> $queue */
        $queue = new SplQueue;
        $queue->enqueue([$this->rootNode($root), null]);

        while (! $queue->isEmpty()) {
            [$node, $ancestors] = $queue->dequeue();
            $signal = yield $node;

            if ($signal === Visit::Stop) {
                return;
            }

            if ($signal === Visit::SkipChildren) {
                continue;
            }

            $childAncestors = $this->enter($node, $ancestors);

            foreach ($this->childNodes($node) as $child) {
                $queue->enqueue([$child, $childAncestors]);
            }
        }
    }

    /**
     * Visit every node; the visitor may return a Visit to skip children or stop early.
     *
     * @param  Closure(TreeNode): (Visit|null|void)  $visitor
     */
    public function walk(ComponentInterface $root, Closure $visitor, TraversalOrder $order = TraversalOrder::PreOrder): void
    {
        $generator = $this->traverse($root, $order);

        while ($generator->valid()) {
            $signal = $visitor($generator->current());

            if ($signal === Visit::Stop) {
                return;
            }

            $generator->send($signal instanceof Visit ? $signal : Visit::Continue);
        }
    }

    /**
     * Nodes whose component type identifier equals $type or that are instances of the class $type.
     *
     * @return Generator<int, TreeNode, mixed, void>
     */
    public function ofType(ComponentInterface $root, string $type, TraversalOrder $order = TraversalOrder::PreOrder): Generator
    {
        foreach ($this->traverse($root, $order) as $node) {
            if ($node->component->type() === $type || $node->component instanceof $type) {
                yield $node;
            }
        }
    }

    public function rootNode(ComponentInterface $root): TreeNode
    {
        return new TreeNode($root, ComponentPath::root(self::segment($root, 0, [])), 0, 0, null);
    }

    /**
     * The direct children of a node, with their deterministic paths.
     *
     * @return list<TreeNode>
     */
    public function childNodes(TreeNode $node): array
    {
        $nodes = [];
        $usedSegments = [];
        $depth = $node->depth + 1;

        foreach ($node->component->children() as $index => $child) {
            $segment = self::segment($child, $index, $usedSegments);
            $usedSegments[$segment] = true;
            $nodes[] = new TreeNode($child, $node->path->child($segment), $depth, $index, $node->path);
        }

        if ($nodes !== [] && $depth > $this->maxDepth) {
            throw InvalidChildException::nestingTooDeep($nodes[0]->path->toString(), $this->maxDepth);
        }

        return $nodes;
    }

    /**
     * Record $node as an ancestor of its children, rejecting cycles.
     */
    private function enter(TreeNode $node, ?AncestorChain $ancestors): AncestorChain
    {
        $ancestors = new AncestorChain(spl_object_id($node->component), $ancestors);

        foreach ($node->component->children() as $child) {
            if ($ancestors->contains(spl_object_id($child))) {
                throw InvalidChildException::cycle($node->path->toString(), $child::class);
            }
        }

        return $ancestors;
    }

    /**
     * @param  array<string, true>  $usedSegments
     */
    private static function segment(ComponentInterface $component, int $index, array $usedSegments): string
    {
        $name = $component->get(Component::Name);

        if (! is_string($name) || preg_match(self::SimpleSegmentPattern, $name) !== 1) {
            return '#'.$index;
        }

        return isset($usedSegments[$name]) ? $name.'#'.$index : $name;
    }
}
