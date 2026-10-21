<?php

declare(strict_types=1);

use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\Field;
use Gcego\Components\Schema\Type;

it('builds immutable fluent fields while preserving absent and null defaults', function (): void {
    $original = Field::string('name');
    $required = $original->mustExist();
    $nullable = $original->nullable()->defaultsTo(null);
    expect($original->build()->required)->toBeFalse()
        ->and($original->build()->hasDefault)->toBeFalse()
        ->and($required->build()->required)->toBeTrue()
        ->and($nullable->build()->hasDefault)->toBeTrue()
        ->and($nullable->build()->default)->toBeNull();
    expect(fn () => Field::boolean('active')->mustExist()->defaultsTo(false))->toThrow(InvalidConfigurationException::class);
});

it('composes schema fields with existing property definitions and modifiers', function (): void {
    $definition = (new ComponentDefinition('test'))->withFields([
        Field::string('name')->mustExist()->description('Name'),
        Field::boolean('active')->defaultsTo(false),
        Field::integer('count')->rule(static fn (mixed $value): bool => is_int($value) && $value > 0, 'positive'),
        Field::string('format')->allowedValues(['plain', 'html'])->normalizeUsing(static fn (mixed $value): mixed => $value),
        Field::of('union', [Type::int(), Type::string()]),
    ]);
    expect($definition->requiredProperties())->toBe(['name'])
        ->and($definition->defaults())->toBe(['active' => false])
        ->and($definition->property('count')->ruleDescription)->toBe('positive')
        ->and($definition->property('union')->acceptsType(42))->toBeTrue();
});
