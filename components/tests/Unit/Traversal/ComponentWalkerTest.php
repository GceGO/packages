<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Exceptions\InvalidChildException;
use Gcego\Components\Properties\Property;
use Gcego\Components\Tests\Fixtures\ChildComponent;
use Gcego\Components\Tests\Fixtures\CyclicComponent;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Components\Traversal\TraversalOrder;
use Gcego\Components\Traversal\Visit;
use Gcego\Components\Trees\TreeNode;
use Illuminate\Container\Container;

/**
 * root
 * ├── a
 * │   ├── a1
 * │   └── a2
 * └── b
 *     └── b1
 */
function walkerTree(): Component
{
    return new Component(
        new Property(Component::Name, 'root'),
        new ChildComponent('a', new ChildComponent('a1'), new ChildComponent('a2')),
        new ChildComponent('b', new ChildComponent('b1')),
    );
}

/**
 * @param  iterable<TreeNode>  $nodes
 * @return list<string>
 */
function walkedPaths(iterable $nodes): array
{
    $paths = [];

    foreach ($nodes as $node) {
        $paths[] = $node->path->toString();
    }

    return $paths;
}

it('traverses in pre-order', function (): void {
    expect(walkedPaths((Container::getInstance()->make(ComponentWalker::class))->preOrder(walkerTree())))
        ->toBe(['root', 'root.a', 'root.a.a1', 'root.a.a2', 'root.b', 'root.b.b1']);
});

it('traverses in post-order', function (): void {
    expect(walkedPaths((Container::getInstance()->make(ComponentWalker::class))->postOrder(walkerTree())))
        ->toBe(['root.a.a1', 'root.a.a2', 'root.a', 'root.b.b1', 'root.b', 'root']);
});

it('traverses breadth-first', function (): void {
    expect(walkedPaths((Container::getInstance()->make(ComponentWalker::class))->breadthFirst(walkerTree())))
        ->toBe(['root', 'root.a', 'root.b', 'root.a.a1', 'root.a.a2', 'root.b.b1']);
});

it('selects the order through traverse()', function (TraversalOrder $order, string $first): void {
    expect(walkedPaths((Container::getInstance()->make(ComponentWalker::class))->traverse(walkerTree(), $order))[0])->toBe($first);
})->with([
    [TraversalOrder::PreOrder, 'root'],
    [TraversalOrder::PostOrder, 'root.a.a1'],
    [TraversalOrder::BreadthFirst, 'root'],
]);

it('reports depth, index and parent path', function (): void {
    $nodes = iterator_to_array((Container::getInstance()->make(ComponentWalker::class))->preOrder(walkerTree()), false);

    expect($nodes[0]->depth)->toBe(0)
        ->and($nodes[0]->isRoot())->toBeTrue()
        ->and($nodes[0]->parentPath)->toBeNull()
        ->and($nodes[3]->depth)->toBe(2)
        ->and($nodes[3]->index)->toBe(1)
        ->and($nodes[3]->parentPath?->toString())->toBe('root.a');
});

it('filters by type identifier or class', function (): void {
    $walker = Container::getInstance()->make(ComponentWalker::class);

    expect(walkedPaths($walker->ofType(walkerTree(), 'fixtures.child')))->toHaveCount(5)
        ->and(walkedPaths($walker->ofType(walkerTree(), Component::class, TraversalOrder::BreadthFirst)))->toHaveCount(6);
});

it('stops early when the consumer breaks', function (): void {
    $visited = 0;

    foreach ((Container::getInstance()->make(ComponentWalker::class))->preOrder(walkerTree()) as $node) {
        $visited++;

        if ($node->path->toString() === 'root.a') {
            break;
        }
    }

    expect($visited)->toBe(2);
});

it('lets visitors skip children or stop', function (TraversalOrder $order, Visit $signal, array $expected): void {
    $visited = [];

    (Container::getInstance()->make(ComponentWalker::class))->walk(walkerTree(), function (TreeNode $node) use (&$visited, $signal): ?Visit {
        $visited[] = $node->path->toString();

        return $node->path->toString() === 'root.a' ? $signal : null;
    }, $order);

    expect($visited)->toBe($expected);
})->with([
    'pre-order skip' => [TraversalOrder::PreOrder, Visit::SkipChildren, ['root', 'root.a', 'root.b', 'root.b.b1']],
    'pre-order stop' => [TraversalOrder::PreOrder, Visit::Stop, ['root', 'root.a']],
    'breadth-first skip' => [TraversalOrder::BreadthFirst, Visit::SkipChildren, ['root', 'root.a', 'root.b', 'root.b.b1']],
    'breadth-first stop' => [TraversalOrder::BreadthFirst, Visit::Stop, ['root', 'root.a']],
    'post-order stop' => [TraversalOrder::PostOrder, Visit::Stop, ['root.a.a1', 'root.a.a2', 'root.a']],
    'post-order ignores skip' => [TraversalOrder::PostOrder, Visit::SkipChildren, ['root.a.a1', 'root.a.a2', 'root.a', 'root.b.b1', 'root.b', 'root']],
]);

it('stops generators when Visit::Stop is sent directly', function (): void {
    $generator = (Container::getInstance()->make(ComponentWalker::class))->postOrder(walkerTree());
    $generator->current();
    $generator->send(Visit::Stop);

    expect($generator->valid())->toBeFalse();
});

it('detects cycles in every order', function (TraversalOrder $order): void {
    iterator_to_array((Container::getInstance()->make(ComponentWalker::class))->traverse(new Component(new CyclicComponent), $order));
})->with([TraversalOrder::PreOrder, TraversalOrder::PostOrder, TraversalOrder::BreadthFirst])
    ->throws(InvalidChildException::class, 'Circular component reference detected at "#0.#0"');

it('rejects nesting beyond the maximum depth', function (): void {
    $component = new ChildComponent('leaf');

    for ($level = 0; $level < 5; $level++) {
        $component = new ChildComponent('n'.$level, $component);
    }

    iterator_to_array((Container::getInstance()->make(ComponentWalker::class, ['maxDepth' => 3]))->preOrder($component));
})->throws(InvalidChildException::class, 'exceeds the maximum depth of 3');

it('walks very deep trees without recursion', function (): void {
    $component = new ChildComponent('leaf');

    for ($level = 0; $level < 2000; $level++) {
        $component = new ChildComponent('n', $component);
    }

    $nodes = iterator_to_array((Container::getInstance()->make(ComponentWalker::class, ['maxDepth' => 5000]))->postOrder($component), false);

    expect($nodes)->toHaveCount(2001)
        ->and($nodes[0]->depth)->toBe(2000);
});

it('labels unnamed, oddly named and duplicate siblings deterministically', function (): void {
    $root = new Component(
        new ChildComponent('same'),
        new ChildComponent('same'),
        new Component(new Property(Component::Name, 'has space')),
        new Component,
    );

    expect(walkedPaths((Container::getInstance()->make(ComponentWalker::class))->preOrder($root)))->toBe(['#0', '#0.same', '#0.same#1', '#0.#2', '#0.#3']);
});
