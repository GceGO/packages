<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\ComponentTree;
use Gcego\Components\Exceptions\DuplicatePropertyException;
use Gcego\Components\Exceptions\InvalidComponentException;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Exceptions\MissingPropertyException;
use Gcego\Components\Property;
use Gcego\Components\Tests\Fixtures\Alignment;
use Gcego\Components\Tests\Fixtures\ChildComponent;
use Gcego\Components\Tests\Fixtures\ExampleComponent;
use Gcego\Components\Tests\Fixtures\ParentComponent;
use Gcego\Components\Tests\Fixtures\Status;
use Gcego\Components\Tests\Fixtures\Style;

it('can be empty', function (): void {
    $component = new Component;

    expect($component->properties()->isEmpty())->toBeTrue()
        ->and($component->childCount())->toBe(0)
        ->and($component->hasChildren())->toBeFalse()
        ->and($component->configurations()->isEmpty())->toBeTrue()
        ->and($component->type())->toBe(Component::class)
        ->and($component->validate()->isValid())->toBeTrue();
});

it('holds properties only', function (): void {
    $component = new Component(
        new Property(Component::Name, 'example'),
        new Property(Component::Label, 'Example Component'),
    );

    expect($component->get(Component::Name))->toBe('example')
        ->and($component->name())->toBe('example')
        ->and($component->get(Component::Label))->toBe('Example Component')
        ->and($component->properties()->keys())->toBe(['name', 'label'])
        ->and($component->childCount())->toBe(0);
});

it('holds children only', function (): void {
    $child = new Component(new Property(Component::Name, 'child'));
    $component = new Component($child);

    expect($component->properties()->isEmpty())->toBeTrue()
        ->and($component->children()->all())->toBe([$child]);
});

it('separates mixed properties, children and configuration nodes', function (): void {
    $first = new Component(new Property(Component::Name, 'statistics'));
    $second = new Component(new Property(Component::Name, 'activity'));
    $style = new Style('grid gap-4');

    $component = new Component(
        new Property(Component::Name, 'dashboard'),
        $first,
        $style,
        new Property(Component::Label, 'Dashboard'),
        $second,
    );

    expect($component->properties()->keys())->toBe(['name', 'label'])
        ->and($component->children()->all())->toBe([$first, $second])
        ->and($component->configurations()->all())->toBe([$style]);
});

it('preserves child order', function (): void {
    $children = array_map(fn (int $index): Component => new Component(new Property(Component::Name, 'c'.$index)), range(1, 10));
    $component = new Component(...$children);

    expect($component->children()->all())->toBe($children);
});

it('rejects duplicate properties', function (): void {
    new Component(new Property(Component::Name, 'a'), new Property(Component::Name, 'b'));
})->throws(DuplicatePropertyException::class, 'Property "name" is declared more than once on '.Component::class);

it('rejects unsupported constructor arguments', function (mixed $argument, string $message): void {
    expect(fn () => new Component($argument))->toThrow(InvalidConfigurationException::class, $message);
})->with([
    'string' => ['name', 'Unsupported constructor argument #1 (string "name")'],
    'int' => [5, 'Component accepts no positional shorthand values'],
    'array' => [[new Property(Component::Name, 'x')], 'array(1)'],
    'object' => [new stdClass, 'stdClass'],
    'null' => [null, 'null'],
]);

it('rejects named arguments', function (): void {
    new Component(name: 'dashboard');
})->throws(InvalidConfigurationException::class, 'Named argument "name" is not supported');

it('stores string-backed enum cases given to string properties as their values', function (): void {
    $component = new ExampleComponent('name', new Property(ExampleComponent::Label, Status::Published), new Property(ExampleComponent::Status, Status::Published));

    expect($component->get(ExampleComponent::Label))->toBe('published')
        ->and($component->get(ExampleComponent::Status))->toBe(Status::Published)   // typed for the enum: kept
        ->and($component->withProperty(new Property(ExampleComponent::Label, Status::Draft))->get(ExampleComponent::Label))->toBe('draft')
        ->and($component->validate()->isValid())->toBeTrue();
});

it('leaves unit enums on string properties invalid', function (): void {
    $component = new ExampleComponent('name', new Property(ExampleComponent::Label, Alignment::End));

    expect($component->get(ExampleComponent::Label))->toBe(Alignment::End)
        ->and($component->validate()->isValid())->toBeFalse();
});

it('returns defaults for absent properties', function (): void {
    $component = new ExampleComponent('name');

    expect($component->get(ExampleComponent::Searchable))->toBeFalse()
        ->and($component->get(ExampleComponent::Status))->toBe(Status::Draft)
        ->and($component->has(ExampleComponent::Searchable))->toBeTrue()
        ->and($component->hasExplicit(ExampleComponent::Searchable))->toBeFalse()
        ->and($component->defaults())->toBe(ExampleComponent::definition()->defaults());
});

it('prefers explicit values over defaults, including explicit null', function (): void {
    $component = new ExampleComponent('name', new Property(ExampleComponent::PerPage, null));

    expect($component->get(ExampleComponent::PerPage))->toBeNull()
        ->and($component->hasExplicit(ExampleComponent::PerPage))->toBeTrue()
        ->and($component->validate()->isValid())->toBeTrue();
});

it('returns the fallback for properties without value or default', function (): void {
    $component = new ExampleComponent('name');

    expect($component->get(Component::Label))->toBeNull()
        ->and($component->get(Component::Label, 'Fallback'))->toBe('Fallback')
        ->and($component->has(Component::Label))->toBeFalse();
});

it('requires a value or default when asked strictly', function (): void {
    $component = new ExampleComponent('name');

    expect($component->require(ExampleComponent::Searchable))->toBeFalse()
        ->and(fn () => $component->require(Component::Label))
        ->toThrow(MissingPropertyException::class, 'Component "fixtures.example" ('.ExampleComponent::class.') has no value for property "label"');
});

it('merges effective properties sorted by key', function (): void {
    $component = new ExampleComponent('name', 'Label', new Property(ExampleComponent::Searchable, true));

    expect($component->effectiveProperties())->toBe([
        'format' => 'plain',
        'label' => 'Label',
        'name' => 'name',
        'perPage' => 10,
        'searchable' => true,
        'status' => Status::Draft,
    ]);
});

it('exposes the explicit id', function (): void {
    expect((new Component(new Property(Component::Id, 'main')))->id())->toBe('main')
        ->and((new Component)->id())->toBeNull()
        ->and((new Component(new Property(Component::Id, 5)))->id())->toBeNull();
});

it('validates required properties', function (): void {
    $result = (new ChildComponent)->validate();

    expect($result->isValid())->toBeFalse()
        ->and($result->first()?->message)->toBe('Missing required property "name" on component "fixtures.child".');
});

it('rejects unknown properties on schema-defined components', function (): void {
    $result = (new ExampleComponent('name', new Property('serchable', true)))->validate();

    expect($result->first()?->message)->toBe('Unknown property "serchable" on component "fixtures.example".')
        ->and($result->first()?->hint)->toBe('Did you mean "searchable"?');
});

it('accepts unknown properties on the generic component', function (): void {
    expect((new Component(new Property('anything', 1)))->validate()->isValid())->toBeTrue();
});

it('validates child types', function (): void {
    $result = (new ParentComponent(new Component))->validate();

    expect($result->first()?->message)->toBe('Component "fixtures.parent" does not accept a child of type "'.Component::class.'".');
});

it('memoizes validation and supports strict mode', function (): void {
    $valid = new ChildComponent('a');

    expect($valid->validate())->toBe($valid->validate())
        ->and($valid->assertValid())->toBe($valid)
        ->and(fn () => (new ChildComponent)->assertValid())->toThrow(InvalidComponentException::class);
});

it('creates a tree', function (): void {
    expect((new Component(new Component))->tree())->toBeInstanceOf(ComponentTree::class)
        ->and((new Component(new Component))->tree()->count())->toBe(2);
});

it('normalizes itself', function (): void {
    expect((new ChildComponent('a'))->normalize()['type'])->toBe('fixtures.child');
});
