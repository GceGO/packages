<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Definitions\DefinitionCatalog;
use Gcego\Blueprints\Definitions\Name;
use Gcego\Blueprints\Exceptions\InvalidDefinitionException;
use Gcego\Blueprints\Tests\Fixtures\Extensions\Permissions;

it('stores distinct identifiers in declaration order', function (): void {
    $permissions = Permissions::make(['view', 'create', 'subjects.export']);

    expect($permissions->key())->toBe('permissions')
        ->and($permissions->value())->toBe(['view', 'create', 'subjects.export'])
        ->and($permissions->contains('create'))->toBeTrue()
        ->and($permissions->contains('delete'))->toBeFalse()
        ->and($permissions)->toHaveCount(3);
});

it('rejects lists that are not distinct lowercase identifiers', function (array $identifiers, string $message): void {
    /** @phpstan-ignore argument.type (the runtime checks are under test) */
    expect(fn () => Permissions::make($identifiers))->toThrow(InvalidDefinitionException::class, $message);
})->with([
    'empty' => [[], 'Invalid "permissions" definition: expected at least one identifier.'],
    'keyed' => [['read' => 'view'], 'expected a list of identifiers, not an array with keys.'],
    'uppercase' => [['View'], 'element 0: "View" is not a lowercase identifier'],
    'not a string' => [['view', 7], 'element 1: int is not a lowercase identifier'],
    'duplicate' => [['view', 'create', 'view'], '"view" is listed twice (elements 0 and 2).'],
]);

it('is stored as data, not as class references', function (): void {
    DefinitionCatalog::register(Permissions::class);

    $blueprint = Blueprint::define(Name::make('subjects'), Permissions::make(['view']));

    expect($blueprint->get('permissions'))->toBe(['view'])
        ->and($blueprint->references())->toBe([]);
});
