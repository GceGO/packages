<?php

declare(strict_types=1);

use Gcego\Blueprints\Contracts\ReferencesClasses;
use Gcego\Blueprints\Definitions\Form;
use Gcego\Blueprints\Definitions\Infolist;
use Gcego\Blueprints\Definitions\Model;
use Gcego\Blueprints\Definitions\Table;
use Gcego\Blueprints\Exceptions\InvalidDefinitionException;

it('stores one class reference under its key', function (string $class, string $key): void {
    $definition = $class::make('App\\Features\\Subjects');

    expect($definition)->toBeInstanceOf(ReferencesClasses::class)
        ->and($definition->key())->toBe($key)
        ->and($definition->value())->toBe('App\\Features\\Subjects')
        ->and($definition->classes())->toBe(['App\\Features\\Subjects']);
})->with([
    'model' => [Model::class, 'model'],
    'table' => [Table::class, 'table'],
    'form' => [Form::class, 'form'],
    'infolist' => [Infolist::class, 'infolist'],
]);

it('removes a leading backslash', function (): void {
    expect(Table::make('\\App\\Tables\\SubjectsTable')->value())->toBe('App\\Tables\\SubjectsTable');
});

it('accepts a class that does not exist without autoloading it', function (): void {
    $requested = [];
    $spy = static function (string $class) use (&$requested): void {
        $requested[] = $class;
    };
    spl_autoload_register($spy);

    $table = Table::make('App\\Missing\\SubjectsTable');
    spl_autoload_unregister($spy);

    expect($table->value())->toBe('App\\Missing\\SubjectsTable')
        ->and($requested)->toBe([]);
});

it('rejects a string that is not a class name', function (string $class): void {
    Table::make($class);
})->throws(InvalidDefinitionException::class, 'is not a valid class name.')->with([
    'empty' => [''],
    'spaces' => ['Subjects Table'],
    'leading digit' => ['App\\1Table'],
    'double separator' => ['App\\\\Table'],
    'trailing separator' => ['App\\Tables\\'],
    'file path' => ['app/Tables/SubjectsTable.php'],
]);
