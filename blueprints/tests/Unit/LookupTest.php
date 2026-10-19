<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Exceptions\BlueprintCompositionException;
use Gcego\Blueprints\Exceptions\DuplicateBlueprintPartException;
use Gcego\Blueprints\Exceptions\MissingBlueprintPartException;
use Gcego\Blueprints\Tests\Fixtures\ExampleAction;
use Gcego\Blueprints\Tests\Fixtures\ExampleForm;
use Gcego\Blueprints\Tests\Fixtures\ExampleTable;

function lookupBlueprint(): Blueprint
{
    return new Blueprint('subjects', new ExampleTable('table'), new ExampleForm('form'), new ExampleAction('create'), new ExampleAction('edit'));
}

it('finds a single part', function (): void {
    $blueprint = lookupBlueprint();

    expect($blueprint->part('table'))->toBeInstanceOf(ExampleTable::class)
        ->and($blueprint->requirePart('form'))->toBeInstanceOf(ExampleForm::class)
        ->and($blueprint->hasPart('table'))->toBeTrue();
});

it('finds multiple parts without dropping any', function (): void {
    $actions = lookupBlueprint()->parts('action');

    expect($actions->count())->toBe(2)
        ->and(array_map(fn ($action): ?string => $action->name(), $actions->all()))->toBe(['create', 'edit'])
        ->and(array_map(fn ($action): ?string => $action->name(), iterator_to_array($actions)))->toBe(['create', 'edit'])
        ->and($actions->get(1)?->name())->toBe('edit')
        ->and($actions->get(9))->toBeNull();
});

it('refuses single lookups on multiple roles', function (): void {
    lookupBlueprint()->part('action');
})->throws(BlueprintCompositionException::class, 'Role "action" of blueprint "subjects" allows several parts; use parts("action") instead of part("action").');

it('refuses strict single lookups on multiple roles too', function (): void {
    lookupBlueprint()->requirePart('action');
})->throws(BlueprintCompositionException::class);

it('handles missing parts', function (): void {
    $blueprint = lookupBlueprint();

    expect($blueprint->part('infolist'))->toBeNull()
        ->and($blueprint->parts('infolist')->isEmpty())->toBeTrue()
        ->and($blueprint->parts('infolist')->first())->toBeNull()
        ->and($blueprint->hasPart('infolist'))->toBeFalse()
        ->and(fn () => $blueprint->requirePart('infolist'))
        ->toThrow(MissingBlueprintPartException::class, 'Blueprint "subjects" has no "infolist" part.');
});

it('refuses to pick one of several single-role parts', function (): void {
    (new Blueprint('subjects', new ExampleTable('a'), new ExampleTable('b')))->part('table');
})->throws(DuplicateBlueprintPartException::class, 'Blueprint "subjects" declares 2 "table" parts (subjects.a, subjects.b), but the role allows only one.');

it('lists all parts and filters by role', function (): void {
    $all = lookupBlueprint()->parts();

    expect($all->count())->toBe(4)
        ->and($all->ofRole('action')->paths())->toBe(['subjects.create', 'subjects.edit'])
        ->and(lookupBlueprint()->roles())->toBe(['table', 'form', 'action']);
});

it('returns immutable collections', function (): void {
    $blueprint = lookupBlueprint();
    $parts = $blueprint->parts('action')->all();
    $parts[] = new ExampleAction('injected');

    expect($blueprint->parts('action')->count())->toBe(2);
});
