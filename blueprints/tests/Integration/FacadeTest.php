<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Contracts\BlueprintResolver;
use Gcego\Blueprints\Definitions\Name;
use Gcego\Blueprints\Exceptions\UnknownBlueprintException;
use Gcego\Blueprints\Facades\Blueprints;
use Gcego\Blueprints\Resolution\RegistryBlueprintResolver;
use Gcego\Registry\Facades\Registry;

it('resolves discovered blueprints by name through the facade', function (): void {
    $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects')");
    $this->workspace->blueprintModule('Modules/Finance/Invoices', "Name::make('invoices')");

    expect(Blueprints::getFacadeRoot())->toBeInstanceOf(RegistryBlueprintResolver::class)
        ->and(Blueprints::getFacadeRoot())->toBe(app(BlueprintResolver::class))
        ->and(Blueprints::names())->toBe(['subjects', 'invoices'])
        ->and(Blueprints::has('subjects'))->toBeTrue()
        ->and(Blueprints::resolve('subjects'))->toBe(Registry::get('blueprint:subjects'))
        ->and(array_keys(Blueprints::all()))->toBe(['subjects', 'invoices']);
});

it('sees blueprints registered at runtime', function (): void {
    $reports = Blueprint::define(Name::make('reports'));
    Registry::register($reports);

    expect(Blueprints::resolve('reports'))->toBe($reports);
});

it('sees blueprints discovered after a registry refresh', function (): void {
    expect(Blueprints::names())->toBe([]);

    $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects')");
    Registry::refresh();

    expect(Blueprints::has('subjects'))->toBeTrue()
        ->and(Blueprints::resolve('subjects')->name())->toBe('subjects');
});

it('throws a blueprint exception for unknown names', function (): void {
    $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects')");

    expect(Blueprints::has('missing'))->toBeFalse()
        ->and(fn () => Blueprints::resolve('missing'))->toThrow(UnknownBlueprintException::class, 'No blueprint named "missing". Known blueprints: subjects.');
});

it('does not discover anything until a lookup', function (): void {
    $this->workspace->blueprintModule('Modules/Academics/Subjects', "Name::make('subjects')");

    Blueprints::getFacadeRoot();
    $this->workspace->blueprintModule('Modules/Finance/Invoices', "Name::make('invoices')");

    expect(Blueprints::names())->toBe(['subjects', 'invoices']);
});
