<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\ComponentCollection;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Tests\Fixtures\ChildComponent;
use Gcego\Components\Tests\Fixtures\ExampleComponent;

it('stores components in order', function (): void {
    $a = new ChildComponent('a');
    $b = new ExampleComponent('b');
    $collection = new ComponentCollection($a, $b);

    expect($collection->all())->toBe([$a, $b])
        ->and($collection->toArray())->toBe([$a, $b])
        ->and(iterator_to_array($collection))->toBe([$a, $b])
        ->and($collection->first())->toBe($a)
        ->and($collection->get(1))->toBe($b)
        ->and($collection->get(5))->toBeNull()
        ->and($collection->has(1))->toBeTrue()
        ->and($collection->has(2))->toBeFalse()
        ->and($collection->count())->toBe(2);
});

it('handles empty collections', function (): void {
    $collection = new ComponentCollection;

    expect($collection->isEmpty())->toBeTrue()
        ->and($collection->first())->toBeNull()
        ->and($collection->all())->toBe([]);
});

it('filters by predicate, type identifier or class', function (): void {
    $a = new ChildComponent('a');
    $b = new ExampleComponent('b');
    $c = new Component;
    $collection = new ComponentCollection($a, $b, $c);

    expect($collection->filter(fn (ComponentInterface $component, int $index): bool => $index > 0)->all())->toBe([$b, $c])
        ->and($collection->ofType('fixtures.example')->all())->toBe([$b])
        ->and($collection->ofType(Component::class)->all())->toBe([$a, $b, $c])
        ->and($collection->ofType(ChildComponent::class)->all())->toBe([$a]);
});

it('cannot be mutated through returned arrays', function (): void {
    $collection = new ComponentCollection(new ChildComponent('a'));
    $all = $collection->all();
    $all[] = new ChildComponent('b');

    expect($collection->count())->toBe(1);
});
