<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Definitions\DefinitionCatalog;
use Gcego\Blueprints\Definitions\Form;
use Gcego\Blueprints\Definitions\Name;
use Gcego\Blueprints\Definitions\Pages;
use Gcego\Blueprints\Definitions\Table;
use Gcego\Blueprints\Definitions\Title;
use Gcego\Blueprints\Exceptions\InvalidBlueprintException;

it('declares blueprints and every built-in definition readonly', function (string $class): void {
    expect((new ReflectionClass($class))->isReadOnly())->toBeTrue();
})->with(array_merge([Blueprint::class], DefinitionCatalog::BuiltIn));

it('replaces a definition in place and leaves the original untouched', function (): void {
    $original = Blueprint::define(Name::make('subjects'), Table::make('App\\Tables\\SubjectsTable'), Title::make('Subjects'));

    $copy = $original->with(Table::make('App\\Tables\\ArchivedSubjectsTable'));

    expect($copy)->not->toBe($original)
        ->and($copy->table())->toBe('App\\Tables\\ArchivedSubjectsTable')
        ->and($copy->keys())->toBe(['name', 'table', 'title'])
        ->and($original->table())->toBe('App\\Tables\\SubjectsTable');
});

it('appends new definitions to a copy', function (): void {
    $original = Blueprint::define(Name::make('subjects'));

    $copy = $original->with(Form::make('App\\Forms\\SubjectForm'));

    expect($copy->keys())->toBe(['name', 'form'])
        ->and($original->keys())->toBe(['name']);
});

it('rejects a key passed twice to with()', function (): void {
    Blueprint::define(Name::make('subjects'))->with(
        Pages::make(['App\\Pages\\ListSubjects']),
        Pages::make(['App\\Pages\\EditSubject']),
    );
})->throws(InvalidBlueprintException::class, 'The "pages" definition is declared twice');

it('removes definitions from a copy', function (): void {
    $original = Blueprint::define(Name::make('subjects'), Table::make('App\\Tables\\SubjectsTable'));

    $copy = $original->without('table');

    expect($copy->has('table'))->toBeFalse()
        ->and($original->has('table'))->toBeTrue();
});

it('refuses to remove the required name', function (): void {
    Blueprint::define(Name::make('subjects'))->without('name');
})->throws(InvalidBlueprintException::class, 'The required "name" definition is missing.');

it('cannot be modified through a returned collection', function (): void {
    $blueprint = Blueprint::define(Name::make('subjects'), Pages::make(['App\\Pages\\ListSubjects']));

    $pages = $blueprint->pages();
    $pages[] = 'App\\Pages\\Injected';

    expect($blueprint->pages())->toBe(['App\\Pages\\ListSubjects']);
});
