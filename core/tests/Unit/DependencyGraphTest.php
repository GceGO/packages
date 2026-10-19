<?php

declare(strict_types=1);

use Gcego\Core\Support\DependencyGraph;

/**
 * @param  array<string, list<string>>  $requires
 * @param  array<string, list<string>>  $optional
 */
function graph(array $requires, array $optional = []): DependencyGraph
{
    $nodes = [];

    foreach ($requires + $optional as $id => $unused) {
        $nodes[$id] = ['requires' => $requires[$id] ?? [], 'optional' => $optional[$id] ?? []];
    }

    return new DependencyGraph($nodes);
}

it('orders dependencies before dependents', function (): void {
    expect(graph(['c' => ['b'], 'b' => ['a'], 'a' => []])->order())->toBe(['a', 'b', 'c']);
});

it('breaks ties alphabetically regardless of insertion order', function (): void {
    expect(graph(['zeta' => [], 'alpha' => [], 'mid' => []])->order())->toBe(['alpha', 'mid', 'zeta'])
        ->and(graph(['d' => ['a'], 'b' => ['a'], 'a' => [], 'c' => []])->order())->toBe(['a', 'b', 'c', 'd']);
});

it('uses optional dependencies for ordering only when present', function (): void {
    expect(graph(['a' => [], 'b' => []], ['a' => ['b']])->order())->toBe(['b', 'a'])
        ->and(graph(['a' => []], ['a' => ['absent']])->order())->toBe(['a'])
        ->and(graph(['a' => []], ['a' => ['absent']])->missing())->toBe([]);
});

it('reports missing required dependencies', function (): void {
    expect(graph(['b' => ['x', 'a'], 'a' => ['y']])->missing())->toBe(['a' => ['y'], 'b' => ['x']]);
});

it('detects cycles', function (): void {
    expect(graph(['a' => ['b'], 'b' => ['c'], 'c' => ['a'], 'd' => []])->cycle())->toBe(['a', 'b', 'c', 'a'])
        ->and(graph(['a' => ['b'], 'b' => ['a']])->order())->toBe([])
        ->and(graph(['a' => []])->cycle())->toBeNull();
});

it('detects cycles through optional dependencies', function (): void {
    expect(graph(['a' => ['b'], 'b' => []], ['b' => ['a']])->cycle())->toBe(['a', 'b', 'a']);
});

it('detects self dependencies', function (): void {
    expect(graph(['a' => ['a']])->cycle())->toBe(['a', 'a']);
});

it('handles long chains without recursion', function (): void {
    $requires = ['n0' => []];

    for ($index = 1; $index < 3000; $index++) {
        $requires['n'.$index] = ['n'.($index - 1)];
    }

    $order = graph($requires)->order();

    expect($order)->toHaveCount(3000)
        ->and($order[0])->toBe('n0')
        ->and($order[2999])->toBe('n2999');
});
