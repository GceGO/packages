<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Properties\Property;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Components\Tests\Fixtures\ChildComponent;
use Gcego\Components\Tests\Fixtures\ExampleComponent;
use Gcego\Components\Tests\Fixtures\OtherConfiguration;
use Gcego\Components\Tests\Fixtures\ParentComponent;
use Gcego\Components\Tests\Fixtures\Placement;
use Gcego\Components\Tests\Fixtures\Status;
use Gcego\Components\Tests\Fixtures\Style;

it('defines the base component as open-ended', function (): void {
    $definition = Component::definition();

    expect($definition->type)->toBe(Component::class)
        ->and($definition->allowsUnknownProperties)->toBeTrue()
        ->and($definition->allowsChildren)->toBeTrue()
        ->and($definition->allowedChildren)->toBeNull()
        ->and($definition->allowedConfigurations)->toBeNull()
        ->and($definition->propertyNames())->toBe([Component::Name, Component::Label, Component::Description, Component::Id]);
});

it('makes subclasses strict about unknown properties by default', function (): void {
    expect(ChildComponent::definition()->allowsUnknownProperties)->toBeFalse()
        ->and(ExampleComponent::definition()->allowsUnknownProperties)->toBeFalse();
});

it('caches definitions per class', function (): void {
    expect(ExampleComponent::definition())->toBe(ExampleComponent::definition())
        ->and(ExampleComponent::definition())->not->toBe(Component::definition());
});

it('exposes required properties and sorted defaults', function (): void {
    $definition = ExampleComponent::definition();

    expect($definition->requiredProperties())->toBe([Component::Name])
        ->and($definition->defaults())->toBe([
            ExampleComponent::Format => 'plain',
            ExampleComponent::PerPage => 10,
            ExampleComponent::Searchable => false,
            ExampleComponent::Status => Status::Draft,
        ]);
});

it('extends parent schemas without changing them', function (): void {
    $base = new ComponentDefinition('base', [new PropertyDefinition('a', Type::string())]);
    $extended = $base->withProperties(new PropertyDefinition('b', Type::int()))
        ->withType('extended')
        ->withUnknownProperties();

    expect($base->propertyNames())->toBe(['a'])
        ->and($base->type)->toBe('base')
        ->and($base->allowsUnknownProperties)->toBeFalse()
        ->and($extended->propertyNames())->toBe(['a', 'b'])
        ->and($extended->type)->toBe('extended')
        ->and($extended->allowsUnknownProperties)->toBeTrue();
});

it('replaces and removes properties', function (): void {
    $definition = (new ComponentDefinition('x', [new PropertyDefinition('a', Type::string()), new PropertyDefinition('b', Type::string())]))
        ->withProperties(new PropertyDefinition('a', Type::int()))
        ->withoutProperties('b');

    expect($definition->propertyNames())->toBe(['a'])
        ->and($definition->property('a')?->expectedType())->toBe('int')
        ->and($definition->property('b'))->toBeNull()
        ->and($definition->hasProperty('b'))->toBeFalse();
});

it('configures child rules', function (): void {
    $definition = (new ComponentDefinition('x'))->withAllowedChildren([ChildComponent::class], min: 1, max: 2);

    expect($definition->allowsChild(new ChildComponent('a')))->toBeTrue()
        ->and($definition->allowsChild(new Component))->toBeFalse()
        ->and($definition->minChildren)->toBe(1)
        ->and($definition->maxChildren)->toBe(2);

    $leaf = $definition->withoutChildren();

    expect($leaf->allowsChildren)->toBeFalse()
        ->and($leaf->allowsChild(new ChildComponent('a')))->toBeFalse()
        ->and($leaf->minChildren)->toBe(0);
});

it('matches allowed classes by inheritance', function (): void {
    $definition = (new ComponentDefinition('x'))->withAllowedChildren([Component::class]);

    expect($definition->allowsChild(new ChildComponent('a')))->toBeTrue();
});

it('configures allowed configuration nodes', function (): void {
    expect(ParentComponent::definition()->allowsConfiguration(new Style('p-4')))->toBeTrue()
        ->and(ParentComponent::definition()->allowsConfiguration(new OtherConfiguration))->toBeFalse()
        ->and(Component::definition()->allowsConfiguration(new OtherConfiguration))->toBeTrue()
        ->and((new ComponentDefinition('x'))->withAllowedConfigurations([])->allowsConfiguration(new Style('')))->toBeFalse();
});

it('accepts placement configuration on every component, whatever its allowed configuration', function (): void {
    $child = new ParentComponent(new ChildComponent(new Property(Component::Name, 'a')), new Placement);

    expect((new ComponentDefinition('x'))->withAllowedConfigurations([])->allowsConfiguration(new Placement))->toBeTrue()
        ->and($child->configurations()->first(Placement::class))->toBeInstanceOf(Placement::class)
        ->and($child->validate()->isValid())->toBeTrue();
});

it('appends constraints', function (): void {
    $definition = (new ComponentDefinition('x'))->withConstraint(fn (): null => null)->withConstraint(fn (): string => 'bad');

    expect($definition->constraints())->toHaveCount(2);
});

it('rejects invalid definitions', function (Closure $factory, string $message): void {
    expect($factory)->toThrow(InvalidConfigurationException::class, $message);
})->with([
    'type' => [fn () => new ComponentDefinition('has space'), 'not a valid component type identifier'],
    'property item' => [fn () => new ComponentDefinition('x', ['name']), 'must be '.PropertyDefinition::class],
    'duplicate property' => [fn () => new ComponentDefinition('x', [new PropertyDefinition('a', Type::string()), new PropertyDefinition('a', Type::int())]), 'defined more than once'],
    'negative min' => [fn () => new ComponentDefinition('x', minChildren: -1), 'Child count bounds'],
    'max below min' => [fn () => new ComponentDefinition('x', minChildren: 2, maxChildren: 1), 'Child count bounds'],
    'required children while disallowed' => [fn () => new ComponentDefinition('x', allowsChildren: false, minChildren: 1), 'cannot require children'],
    'child class' => [fn () => new ComponentDefinition('x', allowedChildren: [stdClass::class]), 'must be an existing class or interface implementing'],
    'configuration class' => [fn () => new ComponentDefinition('x', allowedConfigurations: [Component::class]), 'implementing Gcego\Components\Contracts\ConfigurationNode'],
    'constraint' => [fn () => new ComponentDefinition('x', constraints: ['strlen']), 'must be closures'],
]);

it('accepts namespaced and dotted type identifiers', function (string $type): void {
    expect((new ComponentDefinition($type))->type)->toBe($type);
})->with(['tables.column.text', 'Gcego\\Tables\\Table', 'forms:text-input']);

it('exports schema metadata', function (): void {
    $schema = ParentComponent::definition()->toArray();

    expect($schema['type'])->toBe('fixtures.parent')
        ->and(array_keys($schema['properties']))->toBe(['description', 'id', 'label', 'model', 'name'])
        ->and($schema['allowedChildren'])->toBe([ExampleComponent::class, ChildComponent::class])
        ->and($schema['minChildren'])->toBe(1)
        ->and($schema['maxChildren'])->toBe(3)
        ->and($schema['allowedConfigurations'])->toBe([Style::class])
        ->and($schema['constraintCount'])->toBe(1);
});
