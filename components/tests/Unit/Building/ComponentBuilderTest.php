<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Contracts\Buildable;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Exceptions\InvalidBuilderException;
use Gcego\Components\Tests\Fixtures\ChildComponent;
use Gcego\Components\Tests\Fixtures\ConstructorComponent;
use Gcego\Components\Tests\Fixtures\ExampleComponent;
use Gcego\Components\Tests\Fixtures\Style;

it('builds immutable fluent definitions with named and positional properties', function (): void {
    $original = ExampleComponent::define('name', label: 'Label');
    $changed = $original->searchable()->perPage(20);

    expect($original->build()->get('searchable'))->toBeFalse()
        ->and($changed->build()->get('searchable'))->toBeTrue()
        ->and($changed->build()->get('perPage'))->toBe(20)
        ->and($changed->build()->get('label'))->toBe('Label');
});

it('preserves iterable components as children through every builder entry point', function (string $entry): void {
    $child = new class extends Component implements IteratorAggregate
    {
        public function getIterator(): Traversable
        {
            yield new ChildComponent('iterator-content');
        }
    };

    $builder = match ($entry) {
        'define' => Component::define($child),
        'with' => Component::define()->with($child),
        'schema' => Component::define()->schema($child),
    };

    expect($builder->build()->children()->all())->toBe([$child]);
})->with(['define', 'with', 'schema']);

it('preserves iterable configuration nodes instead of flattening them', function (): void {
    $configuration = new class implements ConfigurationNode, IteratorAggregate
    {
        public function normalize(): array
        {
            return ['style' => 'original'];
        }

        public function getIterator(): Traversable
        {
            yield new Style('iterator-content');
        }
    };

    expect(Component::define($configuration)->build()->configurations()->all())->toBe([$configuration])
        ->and(Component::define()->with($configuration)->build()->configurations()->all())->toBe([$configuration]);
});

it('preserves iterable builders and builds them as children', function (): void {
    $child = new class implements Buildable, IteratorAggregate
    {
        public function build(): Component
        {
            return new ChildComponent('built');
        }

        public function getIterator(): Traversable
        {
            yield from [];
        }
    };

    expect(Component::define()->schema($child)->build()->children()->first()?->get('name'))->toBe('built');
});

it('flattens nested child iterables and skips conditional nulls', function (): void {
    $a = new ChildComponent('a');
    $b = ChildComponent::define('b');
    $children = (function () use ($a, $b): Generator {
        yield 'first' => $a;
        yield 'nested' => [null, $b];
    })();

    $built = Component::define()->schema($children)->build();

    expect($built->childCount())->toBe(2)
        ->and($built->children()->get(0))->toBe($a)
        ->and($built->children()->get(1)?->get('name'))->toBe('b');
});

it('adapts custom constructors without skipping their initialization', function (): void {
    $child = new ChildComponent('child');
    $style = new Style('p-4');
    $component = ConstructorComponent::define('name')->label('Label')->with($child)->configure($style)->build();

    expect($component)->toBeInstanceOf(ConstructorComponent::class)
        ->and($component->initialized)->toBeTrue()
        ->and($component->name())->toBe('name')
        ->and($component->get('label'))->toBe('Label')
        ->and($component->children()->all())->toBe([$child])
        ->and($component->configurations()->all())->toBe([$style])
        ->and($component->validate()->isValid())->toBeTrue();
});

it('reports missing custom constructor values before construction', function (): void {
    expect(fn () => ConstructorComponent::define()->build())
        ->toThrow(InvalidBuilderException::class, 'a string name is required');
});
