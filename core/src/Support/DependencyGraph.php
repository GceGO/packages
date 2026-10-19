<?php

declare(strict_types=1);

namespace Gcego\Core\Support;

/**
 * A small, iterative dependency graph shared by package and extension ordering.
 *
 * Nodes list hard ("requires") and soft ("optional") dependencies. Optional
 * dependencies only affect ordering when the dependency is present. Ordering
 * is Kahn's algorithm with ties broken by identifier, so the result does not
 * depend on registration order.
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
        return $this->sort()[0];
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

        $remaining = $this->sort()[1];

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

        $ready = array_keys(array_filter($pending, static fn (int $count): bool => $count === 0));
        sort($ready);
        $order = [];

        while ($ready !== []) {
            $id = array_shift($ready);
            $order[] = $id;
            unset($pending[$id]);

            foreach ($dependents[$id] ?? [] as $dependent) {
                if (--$pending[$dependent] === 0) {
                    $ready[] = $dependent;
                }
            }

            sort($ready);
        }

        return [$order, array_fill_keys(array_keys($pending), true)];
    }
}
