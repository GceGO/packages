<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Definitions\DefinitionCatalog;
use Gcego\Blueprints\Definitions\Name;
use Gcego\Blueprints\Definitions\ReferenceCollectionDefinition;
use Gcego\Blueprints\Definitions\ReferenceDefinition;
use Gcego\Blueprints\Exceptions\InvalidDefinitionException;
use Gcego\Blueprints\Validation\BlueprintValidator;

dataset('built-in references', function (): iterable {
    foreach (DefinitionCatalog::BuiltIn as $class) {
        if (is_subclass_of($class, ReferenceDefinition::class) || is_subclass_of($class, ReferenceCollectionDefinition::class)) {
            yield $class::definitionKey() => [$class];
        }
    }
});

it('compiles every built-in reference and preserves its shape through copies and serialization', function (string $class): void {
    $collection = is_subclass_of($class, ReferenceCollectionDefinition::class);
    $definition = $class::make($collection ? ['\\App\\Missing\\First', 'App\\Missing\\Second'] : '\\App\\Missing\\First');
    $key = $class::definitionKey();
    $classes = $collection ? ['App\\Missing\\First', 'App\\Missing\\Second'] : ['App\\Missing\\First'];
    $value = $collection ? $classes : $classes[0];
    $blueprint = Blueprint::define(Name::make('subjects'), $definition);

    expect($blueprint->find($class))->toBe($definition)
        ->and($blueprint->get($key))->toBe($value)
        ->and($blueprint->references())->toBe([$key => $classes])
        ->and($blueprint->toArray()['definitions'][$key])->toBe($value);

    $replacement = $class::make($collection ? [stdClass::class] : stdClass::class);
    $copy = $blueprint->with($replacement);

    expect($copy->find($class))->toBe($replacement)
        ->and($blueprint->find($class))->toBe($definition)
        ->and($copy->without($key)->has($key))->toBeFalse();
})->with('built-in references');

it('validates every built-in reference through the integration rules', function (string $class): void {
    $collection = is_subclass_of($class, ReferenceCollectionDefinition::class);
    $definition = $class::make($collection ? ['App\\Missing\\Reference'] : 'App\\Missing\\Reference');
    $result = BlueprintValidator::withDefaults()->validate(Blueprint::define(Name::make('subjects'), $definition));

    expect($result->errorsForKey($class::definitionKey()))->toHaveCount(1)
        ->and($result->first()?->code)->toBe('blueprint.unresolvable_reference');
})->with('built-in references');

it('rejects malformed references for every built-in type', function (string $class): void {
    $value = is_subclass_of($class, ReferenceCollectionDefinition::class) ? ['invalid/class'] : 'invalid/class';

    expect(fn () => $class::make($value))->toThrow(InvalidDefinitionException::class, 'Invalid "'.$class::definitionKey().'" definition');
})->with('built-in references');
