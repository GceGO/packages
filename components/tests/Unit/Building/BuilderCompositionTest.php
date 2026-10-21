<?php

declare(strict_types=1);

use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Component;
use Gcego\Components\Contracts\Buildable;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Exceptions\InvalidBuilderException;
use Gcego\Components\Exceptions\InvalidComponentException;
use Gcego\Components\Tests\Fixtures\ConstructorComponent;
use Gcego\Components\Tests\Fixtures\ConstructorComponentBuilder;
use Gcego\Components\Tests\Fixtures\ExampleComponent;
use Gcego\Components\Tests\Fixtures\Style;

it('merges and selects explicit properties without overwriting null or defaults', function (): void {
    $original = Component::define()->set('nullable', null);
    $changed = $original->setMany(['name' => 'example', 'enabled' => false])->setIfMissing('nullable', 'replacement')->setIfMissing('new', 1);
    expect($original->properties())->toBe(['nullable' => null])
        ->and($changed->get('nullable', 'fallback'))->toBeNull()
        ->and($changed->has('enabled'))->toBeTrue()
        ->and($changed->get('missing', 'fallback'))->toBe('fallback')
        ->and($changed->onlyProperties('name', 'enabled')->properties())->toBe(['name' => 'example', 'enabled' => false])
        ->and($changed->clearProperties()->properties())->toBe([])
        ->and($changed->replaceProperties(['name' => 'replacement'])->properties())->toBe(['name' => 'replacement'])
        ->and($changed->forget('name')->has('name'))->toBeFalse();

    $builder = ExampleComponent::define('field');
    expect($builder->has('perPage'))->toBeFalse()
        ->and($builder->get('perPage', 'absent'))->toBe('absent')
        ->and($builder->setIfMissing('perPage', 20)->build()->get('perPage'))->toBe(20);
});

it('handles streaming duplicate property keys and rejects numeric keys atomically', function (): void {
    $builder = Component::define()->set('name', 'original');
    $properties = (function (): Generator {
        yield 'name' => 'first';
        yield 'name' => 'last';
    })();
    expect($builder->setMany($properties)->get('name'))->toBe('last');
    expect(fn () => $builder->setMany(['name' => 'changed', 0 => 'invalid']))->toThrow(InvalidBuilderException::class)
        ->and($builder->get('name'))->toBe('original');
});

it('prepends replaces and clears children while preserving order and properties', function (): void {
    $original = Component::group('group')->child(Component::item('middle'));
    $changed = $original->prependChildren([null, [Component::item('first')]])->child(Component::item('last'));
    $names = static fn (ComponentBuilder $builder): array => array_map(static fn (ComponentInterface $child): ?string => $child->get('name'), $builder->build()->children()->all());

    expect($names($original))->toBe(['middle'])
        ->and($names($changed))->toBe(['first', 'middle', 'last'])
        ->and($names($changed->replaceChildren(Component::item('replacement'))))->toBe(['replacement'])
        ->and($changed->clearChildren()->childCount())->toBe(0)
        ->and($changed->clearChildren()->build()->name())->toBe('group');
});

it('filters and maps unbuilt child definitions and supports dropping children', function (): void {
    $builds = 0;
    $child = new class($builds) implements Buildable
    {
        public function __construct(private int &$builds) {}

        public function build(): Component
        {
            $this->builds++;

            return Component::item('lazy')->build();
        }
    };
    $original = Component::group('group')->child([$child, Component::item('second')]);
    $filtered = $original->filterChildren(static fn ($child, int $index): bool => $index === 0);
    $mapped = $original->mapChildren(static fn ($child, int $index) => $index === 0 ? $child : null);

    expect($builds)->toBe(0)
        ->and($original->childCount())->toBe(2)
        ->and($filtered->children())->toBe([$child])
        ->and($mapped->children())->toBe([$child]);
    $mapped->build();
    expect($builds)->toBe(1);
    expect(fn () => $original->mapChildren(static fn () => 'invalid'))->toThrow(InvalidBuilderException::class);
});

it('replaces configurations and accepts named variadic configuration entries', function (): void {
    $first = new Style('first');
    $second = new Style('second');
    $original = Component::define()->configure(style: $first);
    expect($original->build()->configurations()->all())->toBe([$first])
        ->and($original->replaceConfigurations(style: $second)->build()->configurations()->all())->toBe([$second])
        ->and($original->clearConfigurations()->build()->configurations()->all())->toBe([]);
});

it('applies only selected branches and composes transformations on custom builders', function (): void {
    $original = ConstructorComponent::define('custom');
    $changed = $original->when(false,
        static fn ($builder) => throw new RuntimeException('wrong branch'),
        static fn ($builder) => $builder->label('Fallback'),
    )->unless(true,
        static fn ($builder) => throw new RuntimeException('wrong branch'),
        static fn ($builder) => $builder->set('description', 'Description'),
    )->apply([
        static fn ($builder) => $builder->label('Final'),
        static fn ($builder) => $builder->child(Component::item('child')),
    ]);

    expect($changed)->toBeInstanceOf(ConstructorComponentBuilder::class)
        ->and($changed->buildValidated()->get('label'))->toBe('Final')
        ->and($changed->build()->initialized)->toBeTrue()
        ->and($changed->childCount())->toBe(1)
        ->and($original->has('label'))->toBeFalse()
        ->and($original->when(false, static fn ($builder) => $builder))->toBe($original);
});

it('offers validated build normalization inspection and tree terminal operations', function (): void {
    $builder = Component::section('dashboard')->child(Component::item('statistics'));
    expect($builder->validate()->isValid())->toBeTrue()
        ->and($builder->normalize())->toBe($builder->build()->normalize())
        ->and($builder->tree()->count())->toBe(2)
        ->and($builder->inspect()->isValid())->toBeTrue()
        ->and(fn () => Component::item('')->buildValidated())->toThrow(InvalidComponentException::class);
});
