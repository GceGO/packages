<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Definitions\DefinitionCatalog;
use Gcego\Blueprints\Definitions\Name;
use Gcego\Blueprints\Exceptions\InvalidDefinitionException;
use Gcego\Blueprints\Tests\Fixtures\Extensions\Navigation;
use Gcego\Blueprints\Tests\Fixtures\Extensions\RawTheme;
use Gcego\Blueprints\Tests\Fixtures\Extensions\Theme;
use Gcego\Blueprints\Tests\Fixtures\PlainTable;
use Gcego\Blueprints\Validation\BlueprintValidator;

it('stores every option in schema order with defaults applied', function (): void {
    $navigation = Navigation::make(' Academics ', icon: 'book-open');

    expect($navigation->key())->toBe('navigation')
        ->and($navigation->value())->toBe(['group' => 'Academics', 'icon' => 'book-open', 'sort' => 0, 'visible' => true, 'badge' => null])
        ->and($navigation->option('icon'))->toBe('book-open')
        ->and($navigation->option('unknown'))->toBeNull()
        ->and($navigation->classes())->toBe([]);
});

it('rejects an option value of the wrong shape', function (Closure $make, string $message): void {
    expect($make)->toThrow(InvalidDefinitionException::class, $message);
})->with([
    'blank required text' => [fn () => Navigation::make('  '), 'Invalid "navigation" definition: option "group": expected non-empty text of at most 200 characters'],
    'invalid icon' => [fn () => Navigation::make('Academics', icon: 'Book Open'), 'option "icon": expected an icon identifier'],
    'integer below the minimum' => [fn () => Navigation::make('Academics', sort: -1), 'option "sort": expected an integer from 0 to 1000, got -1.'],
    'invalid class name' => [fn () => Navigation::make('Academics', badge: 'not a class'), 'option "badge": "not a class" is not a valid class name.'],
    'uppercase identifier' => [fn () => Theme::make('Ocean'), 'option "name": expected a lowercase identifier'],
]);

it('reports class options as references the integration rules check', function (): void {
    DefinitionCatalog::register(Navigation::class);
    $blueprint = Blueprint::define(Name::make('subjects'), Navigation::make('Academics', badge: 'App\\Missing\\Badge'));

    expect($blueprint->references())->toBe(['navigation' => ['App\\Missing\\Badge']])
        ->and(BlueprintValidator::withDefaults()->validate($blueprint)->first()?->code)->toBe('blueprint.unresolvable_reference');
});

it('has no reference entry when no class option is set', function (): void {
    DefinitionCatalog::register(Navigation::class);

    expect(Blueprint::define(Name::make('subjects'), Navigation::make('Academics'))->references())->toBe([]);
});

it('rejects unknown and missing required options when subclasses build from raw options', function (): void {
    expect(fn () => RawTheme::raw(['name' => 'ocean', 'colour' => 'blue']))
        ->toThrow(InvalidDefinitionException::class, 'unknown option(s) "colour". Known options: name, dark.')
        ->and(fn () => RawTheme::raw(['dark' => true]))
        ->toThrow(InvalidDefinitionException::class, 'option "name" is required.');
});

it('accepts a valid class option', function (): void {
    expect(Navigation::make('Academics', badge: PlainTable::class)->classes())->toBe([PlainTable::class]);
});
