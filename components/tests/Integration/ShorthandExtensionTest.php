<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Exceptions\DuplicatePropertyException;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Exceptions\InvalidPropertyException;
use Gcego\Components\Properties\Property;
use Gcego\Components\Tests\Fixtures\ChildComponent;
use Gcego\Components\Tests\Fixtures\ExampleComponent;
use Gcego\Components\Tests\Fixtures\FakeModel;
use Gcego\Components\Tests\Fixtures\ParentComponent;
use Gcego\Components\Tests\Fixtures\Style;

it('produces identical normalized output for shorthand and advanced declarations', function (): void {
    $shorthand = new ExampleComponent('name', 'Subject Name');
    $advanced = new ExampleComponent(
        new Property(ExampleComponent::Name, 'name'),
        new Property(ExampleComponent::Label, 'Subject Name'),
    );

    expect($shorthand->normalize())->toBe($advanced->normalize())
        ->and($shorthand->properties()->toArray())->toBe($advanced->properties()->toArray());
});

it('mixes shorthand values with advanced properties', function (): void {
    $component = new ExampleComponent('name', 'Subject Name', new Property(ExampleComponent::Searchable, true));

    expect($component->get(ExampleComponent::Searchable))->toBeTrue()
        ->and($component->validate()->isValid())->toBeTrue();
});

it('allows fewer shorthand values than declared keys', function (): void {
    expect((new ExampleComponent('name'))->properties()->keys())->toBe(['name']);
});

it('supports the minimal-code container scenario', function (): void {
    $table = new ParentComponent(
        FakeModel::class,
        new ExampleComponent('name', 'Subject Name'),
        new ExampleComponent('code', 'Code'),
    );

    expect($table->get(ParentComponent::Model))->toBe(FakeModel::class)
        ->and($table->childCount())->toBe(2)
        ->and($table->validate()->isValid())->toBeTrue();
});

it('runs shorthand values through the same validation pipeline', function (): void {
    $error = (new ExampleComponent(123))->validate()->first();

    expect($error?->message)->toBe('Invalid property "name" on component "fixtures.example".')
        ->and($error?->received)->toBe('int 123');
});

it('runs shorthand values through the same value safety checks', function (): void {
    new ExampleComponent(fn (): string => 'name');
})->throws(InvalidPropertyException::class, 'Unsupported value for property "name"');

it('detects collisions between shorthand and explicit properties', function (): void {
    new ExampleComponent('name', new Property(ExampleComponent::Name, 'other'));
})->throws(DuplicatePropertyException::class, 'Property "name" is declared more than once on '.ExampleComponent::class);

it('rejects more shorthand values than declared keys', function (): void {
    new ExampleComponent('name', 'Label', 'extra');
})->throws(InvalidConfigurationException::class, 'ExampleComponent accepts 2 positional shorthand value(s): name, label');

it('rejects shorthand values after items', function (): void {
    new ExampleComponent(new Property(ExampleComponent::Label, 'Label'), 'name');
})->throws(InvalidConfigurationException::class, 'Positional shorthand values must precede');

it('does not force shorthand on components that do not declare it', function (): void {
    new Component('name');
})->throws(InvalidConfigurationException::class, 'Component accepts no positional shorthand values');

it('lets each component choose its own shorthand signature', function (): void {
    expect((new ChildComponent('code'))->get(Component::Name))->toBe('code')
        ->and((new ParentComponent(FakeModel::class, new ChildComponent('a')))->get(ParentComponent::Model))->toBe(FakeModel::class);
});

it('never interprets configuration nodes as shorthand or children', function (): void {
    $component = new ParentComponent(FakeModel::class, new Style('p-4'), new ChildComponent('a'));

    expect($component->childCount())->toBe(1)
        ->and($component->configurations()->count())->toBe(1)
        ->and($component->properties()->keys())->toBe(['model']);
});
