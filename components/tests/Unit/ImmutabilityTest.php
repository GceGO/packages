<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Property;
use Gcego\Components\Tests\Fixtures\ChildComponent;
use Gcego\Components\Tests\Fixtures\ExampleComponent;
use Gcego\Components\Tests\Fixtures\ParentComponent;
use Gcego\Components\Tests\Fixtures\Style;

it('copies with a changed property and leaves the original unchanged', function (): void {
    $original = new Component(new Property(Component::Label, 'Original'));
    $updated = $original->withProperty(new Property(Component::Label, 'Updated'));

    expect($updated)->not->toBe($original)
        ->and($original->get(Component::Label))->toBe('Original')
        ->and($updated->get(Component::Label))->toBe('Updated');
});

it('preserves the concrete subtype when copying', function (): void {
    $example = new ExampleComponent('name', 'Label');

    expect($example->withProperty(new Property(ExampleComponent::Searchable, true)))->toBeInstanceOf(ExampleComponent::class)
        ->and($example->withoutProperty(Component::Label))->toBeInstanceOf(ExampleComponent::class)
        ->and((new ParentComponent(new ChildComponent('a')))->withChildren(new ChildComponent('b')))->toBeInstanceOf(ParentComponent::class);
});

it('re-validates copies instead of reusing the original result', function (): void {
    $valid = new ExampleComponent('name');
    expect($valid->validate()->isValid())->toBeTrue();

    $invalid = $valid->withProperty(new Property('serchable', true));

    expect($invalid->validate()->isValid())->toBeFalse()
        ->and($valid->validate()->isValid())->toBeTrue();
});

it('removes properties in copies', function (): void {
    $original = new Component(new Property(Component::Name, 'a'), new Property(Component::Label, 'A'));
    $copy = $original->withoutProperty(Component::Label);

    expect($copy->properties()->keys())->toBe(['name'])
        ->and($original->properties()->keys())->toBe(['name', 'label']);
});

it('replaces and appends children in copies', function (): void {
    $a = new ChildComponent('a');
    $b = new ChildComponent('b');
    $original = new Component($a);

    expect($original->withChildren($b)->children()->all())->toBe([$b])
        ->and($original->withAppendedChildren($b)->children()->all())->toBe([$a, $b])
        ->and($original->children()->all())->toBe([$a]);
});

it('replaces configuration nodes in copies', function (): void {
    $original = new Component(new Style('p-2'));
    $copy = $original->withConfigurations(new Style('p-4'));

    expect($copy->configurations()->first(Style::class)?->classes)->toBe('p-4')
        ->and($original->configurations()->first(Style::class)?->classes)->toBe('p-2');
});

it('does not expose mutable internals through returned arrays', function (): void {
    $child = new ChildComponent('a');
    $component = new Component(new Property(Component::Name, 'parent'), $child);

    $children = $component->children()->all();
    $children[] = new ChildComponent('b');
    $properties = $component->properties()->toArray();
    $properties['name'] = 'changed';
    $effective = $component->effectiveProperties();
    $effective['name'] = 'changed';

    expect($component->childCount())->toBe(1)
        ->and($component->get(Component::Name))->toBe('parent');
});

it('has no public mutable state', function (): void {
    $reflection = new ReflectionClass(Component::class);
    $public = array_filter($reflection->getProperties(), fn (ReflectionProperty $property): bool => $property->isPublic());

    expect($public)->toBe([]);
});

it('keeps arrays stored in properties isolated from later mutation', function (): void {
    $options = ['columns' => 2];
    $component = new Component(new Property('options', $options));
    $options['columns'] = 3;

    $read = $component->get('options');
    expect($read)->toBe(['columns' => 2]);

    if (is_array($read)) {
        $read['columns'] = 4;
    }

    expect($component->get('options'))->toBe(['columns' => 2]);
});

it('can reuse one immutable instance in several positions', function (): void {
    $shared = new ChildComponent('shared');
    $parent = new Component(new Property(Component::Name, 'root'), $shared, $shared);
    $tree = $parent->tree();

    expect($tree->count())->toBe(3)
        ->and($tree->find('root.shared'))->not->toBeNull()
        ->and($tree->find('root.shared#1'))->not->toBeNull()
        ->and($tree->find('root.shared')?->component)->toBe($tree->find('root.shared#1')?->component);
});
