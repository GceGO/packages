<?php

declare(strict_types=1);

namespace Gcego\Navigation\Tree;

use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Components\Trees\TreeNode;
use Gcego\Navigation\Components\Group;
use Gcego\Navigation\Exceptions\DuplicateNavigationItemException;
use Gcego\Navigation\Exceptions\InvalidNavigationException;
use Gcego\Navigation\Navigation;
use Gcego\Navigation\NavigationNode;
use Throwable;

/**
 * Builds a NavigationTree from a definition and its contributions.
 *
 * Rejects (InvalidNavigationException): invalid definitions, cycles and
 * runaway nesting (gcego/morfaw traversal), groups nested deeper than
 * $maxDepth, and duplicate node names (DuplicateNavigationItemException).
 * Orders siblings by Order, then contribution, then declaration order.
 */
final class NavigationTreeBuilder
{
    /** @var array<string, string> */
    private array $paths = [];

    /**
     * @param  list<list<NavigationNode>>  $contributions  Nodes of each contributor, in registration order.
     */
    public function build(Navigation $navigation, string $identity, int $maxDepth, array $contributions = []): NavigationTree
    {
        $this->paths = [];
        $walker = app(ComponentWalker::class);

        try {
            iterator_to_array($walker->preOrder($navigation), false);
            $errors = array_map(strval(...), $navigation->validate()->errors());
        } catch (Throwable $exception) {
            throw InvalidNavigationException::invalid(sprintf('Navigation "%s"', $identity), $exception->getMessage());
        }

        if ($errors !== []) {
            throw InvalidNavigationException::invalid(sprintf('Navigation "%s"', $identity), implode(' ', $errors));
        }

        $root = $walker->rootNode($navigation);
        $nodes = $this->children($walker, $root, 1, $maxDepth, $identity, 0);

        foreach ($contributions as $index => $contributed) {
            foreach ($contributed as $position => $component) {
                try {
                    $errors = array_map(strval(...), $component->validate()->errors());
                } catch (Throwable $exception) {
                    $errors = [$exception->getMessage()];
                }

                if ($component instanceof Navigation || $errors !== []) {
                    throw InvalidNavigationException::invalid(sprintf('A contribution to "%s"', $identity), $component instanceof Navigation ? 'contributors add nodes, not navigations.' : implode(' ', $errors));
                }

                $contributed = $walker->rootNode($component);
                $target = null;

                foreach ($nodes as $key => $existing) {
                    if ($component instanceof Group && $existing->isContainer() && $existing->id === $component->nodeName()) {
                        $target = $key;
                    }
                }

                if ($target === null) {
                    $nodes[] = $this->node($walker, $contributed, '+'.$index.'.'.$position, 1, $maxDepth, $identity, ($index + 1) * 100000 + $position);

                    continue;
                }

                $children = [];

                foreach ($walker->childNodes($contributed) as $child) {
                    $children[] = $this->node($walker, $child, $nodes[$target]->path.'.+'.$index.'.'.$child->index, 2, $maxDepth, $identity, ($index + 1) * 100000 + $child->index);
                }

                $nodes[$target] = $nodes[$target]->withChildren([...$nodes[$target]->children, ...$children]);
            }
        }

        return new NavigationTree($identity, $navigation, self::sort(array_values($nodes)), $navigation->requirements());
    }

    /**
     * @param  list<Node>  $nodes
     * @return list<Node>
     */
    public static function sort(array $nodes): array
    {
        usort($nodes, static fn (Node $a, Node $b): int => [$a->order, $a->position] <=> [$b->order, $b->position]);

        return $nodes;
    }

    /**
     * @return list<Node>
     */
    private function children(ComponentWalker $walker, TreeNode $parent, int $depth, int $maxDepth, string $identity, int $offset): array
    {
        $nodes = [];

        foreach ($walker->childNodes($parent) as $child) {
            $nodes[] = $this->node($walker, $child, $child->path->toString(), $depth, $maxDepth, $identity, $offset + $child->index);
        }

        return self::sort($nodes);
    }

    private function node(ComponentWalker $walker, TreeNode $tree, string $path, int $depth, int $maxDepth, string $identity, int $position): Node
    {
        $component = $tree->component;

        if (! $component instanceof NavigationNode) {
            throw InvalidNavigationException::invalid(sprintf('Navigation "%s"', $identity), sprintf('"%s" is a %s, not a navigation node.', $path, $component::class));
        }

        $type = $component->nodeType();
        $id = $component->nodeName();
        $id = $id !== '' ? $id : $type.'-'.substr(hash('sha256', $path), 0, 8);

        if (isset($this->paths[$id])) {
            throw DuplicateNavigationItemException::forName($identity, $id, $this->paths[$id], $path);
        }

        $this->paths[$id] = $path;
        $children = [];

        if ($type === 'group' || $type === 'dropdown') {
            if ($depth > $maxDepth) {
                throw InvalidNavigationException::invalid(sprintf('Navigation "%s"', $identity), sprintf('group "%s" is nested %d levels deep; gcego-navigation.groups.max_depth allows %d.', $id, $depth, $maxDepth));
            }

            foreach ($walker->childNodes($tree) as $child) {
                $segments = $child->path->segments();
                $segment = $segments === [] ? (string) $child->index : $segments[count($segments) - 1];
                $children[] = $this->node($walker, $child, $path.'.'.$segment, $depth + 1, $maxDepth, $identity, $child->index);
            }
        }

        return new Node($id, $path, $type, $component, $component->requirements(), $component->order(), $position, $depth, self::sort($children));
    }
}
