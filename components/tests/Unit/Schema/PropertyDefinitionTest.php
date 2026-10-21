<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Components\Tests\Fixtures\Alignment;
use Gcego\Components\Tests\Fixtures\FakeModel;
use Gcego\Components\Tests\Fixtures\Money;
use Gcego\Components\Tests\Fixtures\Status;

it('distinguishes an absent default from a null default', function (): void {
    $withoutDefault = new PropertyDefinition('label', Type::string());
    $nullDefault = new PropertyDefinition('label', Type::string(), nullable: true, default: null);

    expect($withoutDefault->hasDefault)->toBeFalse()
        ->and($nullDefault->hasDefault)->toBeTrue()
        ->and($nullDefault->default)->toBeNull();
});

it('checks scalar types strictly', function (): void {
    expect(Type::string()->accepts('1'))->toBeTrue()
        ->and(Type::string()->accepts(1))->toBeFalse()
        ->and(Type::int()->accepts(1))->toBeTrue()
        ->and(Type::int()->accepts('1'))->toBeFalse()
        ->and(Type::int()->accepts(1.0))->toBeFalse()
        ->and(Type::float()->accepts(1))->toBeTrue()
        ->and(Type::float()->accepts(1.5))->toBeTrue()
        ->and(Type::bool()->accepts(0))->toBeFalse()
        ->and(Type::array()->accepts(['a' => 1]))->toBeTrue()
        ->and(Type::list()->accepts([1, 2]))->toBeTrue()
        ->and(Type::list()->accepts(['a' => 1]))->toBeFalse()
        ->and(Type::mixed()->accepts(Status::Draft))->toBeTrue();
});

it('checks enums by case instance', function (): void {
    $type = Type::enum(Status::class);

    expect($type->accepts(Status::Draft))->toBeTrue()
        ->and($type->accepts('draft'))->toBeFalse()
        ->and($type->accepts(Alignment::Start))->toBeFalse()
        ->and($type->describe())->toBe('enum<'.Status::class.'>');
});

it('checks class strings without instantiating them', function (): void {
    FakeModel::$constructed = 0;
    $type = Type::classString(FakeModel::class);

    expect($type->accepts(FakeModel::class))->toBeTrue()
        ->and($type->accepts(Component::class))->toBeFalse()
        ->and($type->accepts('Missing\\ClassName'))->toBeFalse()
        ->and(Type::classString(ComponentInterface::class)->accepts(Component::class))->toBeTrue()
        ->and(Type::classString()->accepts(Component::class))->toBeTrue()
        ->and(Type::classString()->describe())->toBe('class-string')
        ->and(FakeModel::$constructed)->toBe(0);
});

it('checks value object instances', function (): void {
    $type = Type::instanceOf(Money::class);

    expect($type->accepts(new Money(1, 'XAF')))->toBeTrue()
        ->and($type->accepts(['amount' => 1]))->toBeFalse()
        ->and($type->describe())->toBe(Money::class);
});

it('rejects type descriptors for missing classes', function (Closure $factory): void {
    $factory();
})->with([
    'enum' => [fn () => Type::enum(Component::class)],
    'class string' => [fn () => Type::classString('Missing\\ClassName')],
    'instance' => [fn () => Type::instanceOf('Missing\\ClassName')],
])->throws(InvalidConfigurationException::class);

it('supports union types', function (): void {
    $definition = new PropertyDefinition('width', [Type::int(), Type::string()]);

    expect($definition->acceptsType(10))->toBeTrue()
        ->and($definition->acceptsType('full'))->toBeTrue()
        ->and($definition->acceptsType(true))->toBeFalse()
        ->and($definition->expectedType())->toBe('int|string');
});

it('accepts null only when nullable', function (): void {
    expect((new PropertyDefinition('label', Type::string()))->acceptsType(null))->toBeFalse()
        ->and((new PropertyDefinition('label', Type::string(), nullable: true))->acceptsType(null))->toBeTrue()
        ->and((new PropertyDefinition('label', Type::string(), nullable: true))->expectedType())->toBe('string|null');
});

it('checks allowed values strictly', function (): void {
    $definition = new PropertyDefinition('size', [Type::string(), Type::int()], allowedValues: ['sm', 'lg', 1]);

    expect($definition->allowsValue('sm'))->toBeTrue()
        ->and($definition->allowsValue('1'))->toBeFalse()
        ->and($definition->allowsValue(1))->toBeTrue()
        ->and($definition->expectedValues())->toBe('one of string "sm", string "lg", int 1');
});

it('runs custom rules', function (): void {
    $definition = new PropertyDefinition('perPage', Type::int(), rule: fn (mixed $value): bool => $value > 0, ruleDescription: 'integer greater than zero');

    expect($definition->satisfiesRule(5))->toBeTrue()
        ->and($definition->satisfiesRule(0))->toBeFalse()
        ->and($definition->expectedRule())->toBe('integer greater than zero')
        ->and((new PropertyDefinition('x', Type::int(), rule: fn (): bool => true))->expectedRule())->toBe('a value accepted by the custom rule');
});

it('rejects inconsistent definitions', function (Closure $factory, string $message): void {
    expect($factory)->toThrow(InvalidConfigurationException::class, $message);
})->with([
    'bad name' => [fn () => new PropertyDefinition('bad name', Type::string()), 'not a valid property name'],
    'no types' => [fn () => new PropertyDefinition('label', []), 'at least one type'],
    'non type' => [fn () => new PropertyDefinition('label', ['string']), 'must be '.Type::class],
    'required with default' => [fn () => new PropertyDefinition('label', Type::string(), required: true, default: 'x'), 'cannot be required and declare a default'],
    'default of wrong type' => [fn () => new PropertyDefinition('perPage', Type::int(), default: 'ten'), 'Default string "ten" of property "perPage" does not match int'],
    'default not allowed' => [fn () => new PropertyDefinition('size', Type::string(), default: 'xl', allowedValues: ['sm']), 'is not one of string "sm"'],
    'default fails rule' => [fn () => new PropertyDefinition('perPage', Type::int(), default: 0, rule: fn (mixed $value): bool => $value > 0, ruleDescription: 'positive'), 'is not positive'],
    'unsupported default' => [fn () => new PropertyDefinition('x', Type::mixed(), default: new stdClass), 'is not a supported property value'],
    'bad allowed values' => [fn () => new PropertyDefinition('x', Type::mixed(), allowedValues: [[1]]), 'must be scalars or enum cases'],
]);

it('exports schema metadata without closures', function (): void {
    $definition = new PropertyDefinition('perPage', Type::int(), default: 10, rule: fn (): bool => true, ruleDescription: 'positive', description: 'Rows per page');

    expect($definition->toArray())->toBe([
        'name' => 'perPage',
        'types' => ['int'],
        'required' => false,
        'nullable' => false,
        'hasDefault' => true,
        'default' => 10,
        'allowedValues' => null,
        'hasRule' => true,
        'ruleDescription' => 'positive',
        'hasNormalizer' => false,
        'description' => 'Rows per page',
    ]);
});
