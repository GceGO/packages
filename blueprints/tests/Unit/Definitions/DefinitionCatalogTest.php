<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Contracts\BlueprintDefinition;
use Gcego\Blueprints\Definitions\DefinitionCatalog;
use Gcego\Blueprints\Definitions\Name;
use Gcego\Blueprints\Definitions\Table;
use Gcego\Blueprints\Exceptions\DefinitionRegistrationException;
use Gcego\Blueprints\Exceptions\InvalidBlueprintException;
use Gcego\Blueprints\Tests\Fixtures\Extensions\Navigation;
use Gcego\Blueprints\Tests\Fixtures\ImpostorTable;

it('lists the built-in definition keys with only the name required', function (): void {
    expect(array_keys(DefinitionCatalog::all()))->toBe([
        'name', 'title', 'description', 'icon', 'model', 'policy', 'table', 'form', 'infolist',
        'pages', 'actions', 'bulk_actions', 'relations', 'widgets', 'derive',
    ])->and(array_map(static fn ($type): string => $type->key, DefinitionCatalog::required()))->toBe(['name']);
});

it('leaves capabilities of other packages to those packages', function (string $key): void {
    expect(DefinitionCatalog::has($key))->toBeFalse();
})->with(['navigation', 'permissions', 'panels', 'theme', 'settings', 'tenancy']);

it('accepts an extension definition once it is registered', function (): void {
    DefinitionCatalog::register(Navigation::class);

    $blueprint = Blueprint::define(Name::make('subjects'), Navigation::make('Academics', sort: 3));

    expect($blueprint->find(Navigation::class)?->option('group'))->toBe('Academics')
        ->and($blueprint->get('navigation'))->toBe(['group' => 'Academics', 'icon' => null, 'sort' => 3, 'visible' => true, 'badge' => null])
        ->and($blueprint->references())->toBe([]);
});

it('ignores a repeated registration of the same class', function (): void {
    $first = DefinitionCatalog::register(Navigation::class);

    expect(DefinitionCatalog::register(Navigation::class))->toBe($first);
});

it('requires a registered required extension in every blueprint', function (): void {
    DefinitionCatalog::register(Navigation::class, required: true);

    Blueprint::define(Name::make('subjects'));
})->throws(InvalidBlueprintException::class, 'The required "navigation" definition is missing.');

it('refuses a second class for a taken key', function (): void {
    DefinitionCatalog::register(ImpostorTable::class);
})->throws(DefinitionRegistrationException::class, 'The blueprint definition key "table" already belongs to '.Table::class);

it('refuses a class that is not a definition', function (): void {
    /** @phpstan-ignore argument.type (the runtime check is under test) */
    DefinitionCatalog::register(stdClass::class);
})->throws(DefinitionRegistrationException::class, 'stdClass cannot be registered');

it('refuses an invalid key', function (): void {
    $definition = new class implements BlueprintDefinition
    {
        public static function definitionKey(): string
        {
            return 'Navigation Group';
        }

        public function key(): string
        {
            return self::definitionKey();
        }

        public function value(): mixed
        {
            return null;
        }
    };

    DefinitionCatalog::register($definition::class);
})->throws(DefinitionRegistrationException::class, 'declares the invalid definition key "Navigation Group"');

it('forgets extensions on reset but keeps built-ins', function (): void {
    DefinitionCatalog::register(Navigation::class);

    DefinitionCatalog::reset();

    expect(DefinitionCatalog::has('navigation'))->toBeFalse()
        ->and(DefinitionCatalog::has('table'))->toBeTrue();
});

it('refuses a required type once blueprints have been defined', function (): void {
    Blueprint::define(Name::make('subjects'));

    DefinitionCatalog::register(Navigation::class, required: true);
})->throws(DefinitionRegistrationException::class, 'cannot be registered as required: blueprints were already defined without it.');

it('accepts an optional type after blueprints have been defined', function (): void {
    Blueprint::define(Name::make('subjects'));

    expect(DefinitionCatalog::register(Navigation::class)->required)->toBeFalse();
});
