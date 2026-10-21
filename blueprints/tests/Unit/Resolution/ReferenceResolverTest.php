<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Definitions\Model;
use Gcego\Blueprints\Definitions\Name;
use Gcego\Blueprints\Definitions\Pages;
use Gcego\Blueprints\Definitions\Table;
use Gcego\Blueprints\Exceptions\InvalidReferenceException;
use Gcego\Blueprints\Resolution\ReferenceResolver;
use Gcego\Blueprints\Tests\Fixtures\ContractTable;
use Gcego\Blueprints\Tests\Fixtures\PlainTable;
use Gcego\Blueprints\Tests\Fixtures\TableContract;
use Gcego\Blueprints\Validation\BlueprintValidator;

it('returns references that pass every rule', function (): void {
    $blueprint = Blueprint::define(Name::make('subjects'), Table::make(ContractTable::class), Pages::make([PlainTable::class, ContractTable::class]));
    $resolver = new ReferenceResolver(BlueprintValidator::withDefaults());

    expect($resolver->one($blueprint, Table::Key, TableContract::class))->toBe(ContractTable::class)
        ->and($resolver->all($blueprint, Pages::Key))->toBe([PlainTable::class, ContractTable::class])
        ->and($resolver->one($blueprint, Model::Key))->toBeNull();
});

it('refuses a reference that cannot be autoloaded before an engine can execute it', function (): void {
    $blueprint = Blueprint::define(Name::make('subjects'), Table::make('App\\Missing\\SubjectsTable'));

    (new ReferenceResolver(BlueprintValidator::withDefaults()))->one($blueprint, Table::Key);
})->throws(InvalidReferenceException::class, "Blueprint \"subjects\" has an invalid \"table\" reference:\nApp\\Missing\\SubjectsTable does not exist or cannot be autoloaded.");

it('refuses a reference that does not implement the contract the engine expects', function (): void {
    $blueprint = Blueprint::define(Name::make('subjects'), Table::make(PlainTable::class));

    (new ReferenceResolver(BlueprintValidator::withDefaults()))->one($blueprint, Table::Key, TableContract::class);
})->throws(InvalidReferenceException::class, 'the "table" reference '.PlainTable::class.' must implement '.TableContract::class);

it('checks only the requested key', function (): void {
    $blueprint = Blueprint::define(Name::make('subjects'), Model::make('App\\Missing\\Subject'), Table::make(PlainTable::class));

    expect((new ReferenceResolver(BlueprintValidator::withDefaults()))->one($blueprint, Table::Key))->toBe(PlainTable::class);
});
