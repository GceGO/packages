<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Properties\Property;
use Gcego\Components\Tests\Fixtures\ChildComponent;
use Gcego\Components\Tests\Fixtures\ExampleComponent;
use Gcego\Components\Trees\ComponentPath;
use Gcego\Components\Trees\TreeNode;

function sampleTree(): Component
{
    return new Component(
        new Property(Component::Name, 'dashboard'),
        new ChildComponent('statistics', new ChildComponent('visits', new Property(Component::Id, 'visits-counter'))),
        new ChildComponent('activity'),
        new ExampleComponent('column'),
    );
}

it('counts nodes, descendants and depth', function (): void {
    $tree = sampleTree()->tree();

    expect($tree->count())->toBe(5)
        ->and($tree->descendantCount())->toBe(4)
        ->and($tree->descendantCount('dashboard.statistics'))->toBe(1)
        ->and($tree->descendantCount('dashboard.activity'))->toBe(0)
        ->and($tree->descendantCount('missing'))->toBe(0)
        ->and($tree->depth())->toBe(2);
});

it('handles a lone root', function (): void {
    $tree = (new Component)->tree();

    expect($tree->count())->toBe(1)
        ->and($tree->depth())->toBe(0)
        ->and($tree->descendantCount())->toBe(0)
        ->and($tree->childrenOf($tree->rootNode()))->toBe([])
        ->and($tree->parentOf($tree->rootNode()))->toBeNull();
});

it('finds nodes by path', function (): void {
    $tree = sampleTree()->tree();

    expect($tree->find('dashboard.statistics.visits')?->component->id())->toBe('visits-counter')
        ->and($tree->find(ComponentPath::fromSegments(['dashboard', 'activity'])))->not->toBeNull()
        ->and($tree->find('dashboard.missing'))->toBeNull()
        ->and($tree->contains('dashboard'))->toBeTrue()
        ->and($tree->contains('nope'))->toBeFalse();
});

it('finds nodes by explicit id', function (): void {
    $tree = sampleTree()->tree();

    expect($tree->findById('visits-counter')?->path->toString())->toBe('dashboard.statistics.visits')
        ->and($tree->findById('missing'))->toBeNull();
});

it('finds nodes by type identifier or class', function (): void {
    $tree = sampleTree()->tree();

    expect($tree->findByType('fixtures.child'))->toHaveCount(3)
        ->and($tree->findByType(ExampleComponent::class))->toHaveCount(1)
        ->and($tree->findByType('unknown'))->toBe([]);
});

it('inspects parents and children', function (): void {
    $tree = sampleTree()->tree();

    expect($tree->parentOf('dashboard.statistics.visits')?->path->toString())->toBe('dashboard.statistics')
        ->and($tree->parentOf('missing'))->toBeNull()
        ->and(array_map(fn (TreeNode $node): string => $node->path->toString(), $tree->childrenOf('dashboard')))
        ->toBe(['dashboard.statistics', 'dashboard.activity', 'dashboard.column'])
        ->and($tree->childrenOf('missing'))->toBe([]);
});

it('iterates depth-first and breadth-first', function (): void {
    $tree = sampleTree()->tree();
    $paths = fn (array $nodes): array => array_map(fn (TreeNode $node): string => $node->path->toString(), $nodes);

    expect($paths($tree->depthFirst()))->toBe(['dashboard', 'dashboard.statistics', 'dashboard.statistics.visits', 'dashboard.activity', 'dashboard.column'])
        ->and($paths($tree->breadthFirst()))->toBe(['dashboard', 'dashboard.statistics', 'dashboard.activity', 'dashboard.column', 'dashboard.statistics.visits'])
        ->and($paths(iterator_to_array($tree)))->toBe($paths($tree->depthFirst()))
        ->and($tree->root())->toBeInstanceOf(Component::class);
});

it('reports duplicate explicit ids', function (): void {
    $tree = (new Component(
        new Property(Component::Name, 'root'),
        new ChildComponent('a', new Property(Component::Id, 'dup')),
        new ChildComponent('b', new Property(Component::Id, 'dup')),
        new ChildComponent('c', new Property(Component::Id, 'unique')),
    ))->tree();

    expect($tree->duplicateIds())->toBe(['dup' => ['root.a', 'root.b']]);
});

it('provides occurrence identities distinct from type and explicit id', function (): void {
    $tree = sampleTree()->tree();
    $visits = $tree->find('dashboard.statistics.visits')?->identity();
    $activity = $tree->find('dashboard.activity')?->identity();

    expect($visits?->type)->toBe('fixtures.child')
        ->and($visits?->explicitId)->toBe('visits-counter')
        ->and($visits?->hasExplicitId())->toBeTrue()
        ->and($visits?->occurrenceKey())->toBe('dashboard.statistics.visits')
        ->and($visits?->stableKey())->toBe('id:visits-counter')
        ->and($activity?->hasExplicitId())->toBeFalse()
        ->and($activity?->stableKey())->toBe('path:dashboard.activity');
});

it('models paths as value objects', function (): void {
    $path = ComponentPath::fromString('a.b.c');

    expect($path->segments())->toBe(['a', 'b', 'c'])
        ->and($path->depth())->toBe(2)
        ->and($path->last())->toBe('c')
        ->and($path->parent()?->toString())->toBe('a.b')
        ->and(ComponentPath::root('a')->parent())->toBeNull()
        ->and($path->child('d')->toString())->toBe('a.b.c.d')
        ->and((string) $path)->toBe('a.b.c')
        ->and(ComponentPath::fromString('a')->isAncestorOf($path))->toBeTrue()
        ->and($path->isAncestorOf($path))->toBeFalse()
        ->and(ComponentPath::fromString('a.x')->isAncestorOf($path))->toBeFalse()
        ->and($path->equals(ComponentPath::fromString('a.b.c')))->toBeTrue()
        ->and(fn () => ComponentPath::fromSegments([]))->toThrow(InvalidConfigurationException::class);
});

it('keeps explicit ids distinct from identical occurrence paths in state keys', function (): void {
    $root = new Component(
        new Property(Component::Name, 'root'),
        new ChildComponent('a'),
        new ChildComponent('b', new Property(Component::Id, 'root.a')),
    );
    $nodes = $root->tree()->depthFirst();
    $keys = array_map(fn (TreeNode $node): string => $node->identity()->stableKey(), $nodes);

    expect($root->validate()->isValid())->toBeTrue()
        ->and($keys)->toBe(['path:root', 'path:root.a', 'id:root.a'])
        ->and(array_unique($keys))->toHaveCount(3);
});

it('rejects foreign tree nodes even when their paths match', function (): void {
    $tree = sampleTree()->tree();
    $foreign = sampleTree()->tree()->find('dashboard.statistics');

    expect($foreign)->not->toBeNull()
        ->and($tree->parentOf($foreign))->toBeNull()
        ->and($tree->childrenOf($foreign))->toBe([])
        ->and($tree->descendantCount($foreign))->toBe(0);
});

it('resolves matching component occurrences to canonical tree metadata', function (): void {
    $tree = sampleTree()->tree();
    $node = $tree->find('dashboard.statistics');
    $spoofed = new TreeNode(component: $node->component, path: $node->path, depth: 0, index: 999, parentPath: null);

    expect($tree->parentOf($spoofed))->toBe($tree->rootNode())
        ->and($tree->childrenOf($spoofed))->toHaveCount(1)
        ->and($tree->descendantCount($spoofed))->toBe(1);
});
