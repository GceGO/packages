<?php

declare(strict_types=1);

namespace Gcego\Core\Support;

use SplMinHeap;

/**
 * A small, iterative dependency graph shared by package and extension ordering.
 *
 * Nodes list hard ("requires") and soft ("optional") dependencies. Optional
 * dependencies only affect ordering when the dependency is present. Ordering
 * is Kahn's algorithm with ties broken by identifier, so the result does not
 * depend on registration order. The order is computed once, on construction,
 * in O((V + E) log V).
 *
 * @internal
 */
final readonly class DependencyGraph
{
    /**
     * @var array<string, list<string>> node => dependencies present in the graph
     */
    private array $edges;

    /**
     * @var list<string>
     */
    private array $order;

    /**
     * @var array<string, true> nodes left in cycles
     */
    private array $remaining;

    /**
     * @param  array<string, array{requires: list<string>, optional: list<string>}>  $nodes
     */
    public function __construct(private array $nodes)
    {
        $edges = [];

        foreach ($nodes as $id => $node) {
            $edges[$id] = array_values(array_unique(array_filter(
                [...$node['requires'], ...$node['optional']],
                fn (string $dependency): bool => isset($nodes[$dependency]) && $dependency !== $id,
            )));
        }

        $this->edges = $edges;
        [$this->order, $this->remaining] = $this->sort();
    }

    /**
     * Required dependencies that are not nodes of the graph, per node.
     *
     * @return array<string, list<string>>
     */
    public function missing(): array
    {
        $missing = [];

        foreach ($this->nodes as $id => $node) {
            $absent = array_values(array_filter(
                $node['requires'],
                fn (string $dependency): bool => ! isset($this->nodes[$dependency]),
            ));

            if ($absent !== []) {
                $missing[$id] = $absent;
            }
        }

        ksort($missing);

        return $missing;
    }

    /**
     * Dependencies first, then dependents. Nodes caught in a cycle are omitted.
     *
     * @return list<string>
     */
    public function order(): array
    {
        return $this->order;
    }

    /**
     * One dependency cycle, starting and ending with the same node, or null.
     *
     * A self-dependency is reported as [id, id].
     *
     * @return list<string>|null
     */
    public function cycle(): ?array
    {
        foreach ($this->nodes as $id => $node) {
            if (in_array($id, $node['requires'], true) || in_array($id, $node['optional'], true)) {
                return [$id, $id];
            }
        }

        $remaining = $this->remaining;

        if ($remaining === []) {
            return null;
        }

        // Every remaining node depends on another remaining node, so walking
        // dependencies must revisit a node within count($remaining) steps.
        $current = min(array_keys($remaining));
        $visited = [];

        while (! isset($visited[$current])) {
            $visited[$current] = count($visited);
            $next = array_values(array_filter($this->edges[$current], fn (string $dependency): bool => isset($remaining[$dependency])));
            sort($next);
            $current = $next[0];
        }

        $path = array_keys($visited);
        $cycle = array_slice($path, $visited[$current]);
        $cycle[] = $current;

        return $cycle;
    }

    /**
     * @return array{0: list<string>, 1: array<string, true>} The order and the nodes left in cycles.
     */
    private function sort(): array
    {
        $pending = [];
        $dependents = [];

        foreach ($this->edges as $id => $dependencies) {
            $pending[$id] = count($dependencies);

            foreach ($dependencies as $dependency) {
                $dependents[$dependency][] = $id;
            }
        }

        /** @var SplMinHeap<string> $ready */
        $ready = new SplMinHeap;

        foreach ($pending as $id => $count) {
            if ($count === 0) {
                $ready->insert($id);
            }
        }

        $order = [];

        while (! $ready->isEmpty()) {
            $id = $ready->extract();
            $order[] = $id;
            unset($pending[$id]);

            foreach ($dependents[$id] ?? [] as $dependent) {
                if (--$pending[$dependent] === 0) {
                    $ready->insert($dependent);
                }
            }
        }

        return [$order, array_fill_keys(array_keys($pending), true)];
    }
}
