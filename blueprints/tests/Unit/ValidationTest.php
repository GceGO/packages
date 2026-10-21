<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Contracts\BlueprintDeriver;
use Gcego\Blueprints\Definitions\Derive;
use Gcego\Blueprints\Definitions\Model;
use Gcego\Blueprints\Definitions\Name;
use Gcego\Blueprints\Definitions\Pages;
use Gcego\Blueprints\Definitions\Table;
use Gcego\Blueprints\Definitions\Title;
use Gcego\Blueprints\Exceptions\InvalidBlueprintException;
use Gcego\Blueprints\Tests\Fixtures\ContractTable;
use Gcego\Blueprints\Tests\Fixtures\PagesDeriver;
use Gcego\Blueprints\Tests\Fixtures\PlainTable;
use Gcego\Blueprints\Tests\Fixtures\TableContract;
use Gcego\Blueprints\Validation\BlueprintValidator;
use Gcego\Blueprints\Validation\ReferenceRules;
use Gcego\Blueprints\Validation\Rules\ImplementsContract;

it('accepts references to existing classes', function (): void {
    $blueprint = Blueprint::define(Name::make('subjects'), Model::make(stdClass::class), Table::make(PlainTable::class));

    expect(BlueprintValidator::withDefaults()->validate($blueprint)->isValid())->toBeTrue();
});

it('reports each reference that cannot be autoloaded', function (): void {
    $blueprint = Blueprint::define(
        Name::make('subjects'),
        Table::make('App\\Missing\\SubjectsTable'),
        Pages::make([PlainTable::class, 'App\\Missing\\ListSubjects']),
    );

    $result = BlueprintValidator::withDefaults()->validate($blueprint);

    expect($result->toArray())->toBe([
        ['code' => 'blueprint.unresolvable_reference', 'message' => 'App\\Missing\\SubjectsTable does not exist or cannot be autoloaded.', 'blueprint' => 'subjects', 'key' => 'table', 'expected' => null, 'received' => 'App\\Missing\\SubjectsTable', 'hint' => null],
        ['code' => 'blueprint.unresolvable_reference', 'message' => 'App\\Missing\\ListSubjects does not exist or cannot be autoloaded.', 'blueprint' => 'subjects', 'key' => 'pages', 'expected' => null, 'received' => 'App\\Missing\\ListSubjects', 'hint' => null],
    ])->and($result->errorsForKey('pages'))->toHaveCount(1);
});

it('checks only structure when no rules are installed', function (): void {
    $blueprint = Blueprint::define(Name::make('subjects'), Table::make('App\\Missing\\SubjectsTable'));

    expect((new BlueprintValidator)->validate($blueprint)->isValid())->toBeTrue();
});

it('applies an adapter contract only to its own definition key', function (): void {
    $validator = new BlueprintValidator(ReferenceRules::defaults()->add(new ImplementsContract('table', TableContract::class)));

    $result = $validator->validate(Blueprint::define(
        Name::make('subjects'),
        Model::make(PlainTable::class),
        Table::make(PlainTable::class),
    ));

    expect($result->messages())->toBe([
        PlainTable::class.' must implement '.TableContract::class." to be used as a \"table\" reference.\nDefinition: table\nReceived: ".PlainTable::class,
    ])->and($result->first()?->code)->toBe('blueprint.incompatible_reference');
});

it('accepts a reference that implements the adapter contract', function (): void {
    $validator = new BlueprintValidator(ReferenceRules::defaults()->add(new ImplementsContract('table', TableContract::class)));

    expect($validator->validate(Blueprint::define(Name::make('subjects'), Table::make(ContractTable::class)))->isValid())->toBeTrue();
});

it('stops at the first failing rule of a reference', function (): void {
    $validator = new BlueprintValidator(ReferenceRules::defaults()->add(new ImplementsContract('table', TableContract::class)));

    $result = $validator->validate(Blueprint::define(Name::make('subjects'), Table::make('App\\Missing\\SubjectsTable')));

    expect($result)->toHaveCount(1)
        ->and($result->first()?->code)->toBe('blueprint.unresolvable_reference');
});

it('sees rules added to the live rule set after the validator was built', function (): void {
    $rules = ReferenceRules::defaults();
    $validator = new BlueprintValidator($rules);

    $rules->add(new ImplementsContract('table', TableContract::class));

    expect($validator->validate(Blueprint::define(Name::make('subjects'), Table::make(PlainTable::class)))->isValid())->toBeFalse();
});

it('requires derivers to implement the deriver contract', function (): void {
    $result = BlueprintValidator::withDefaults()->validate(Blueprint::define(Name::make('subjects'), Derive::make([PlainTable::class, PagesDeriver::class])));

    expect($result->messages())->toHaveCount(1)
        ->and($result->first()?->message)->toBe(PlainTable::class.' must implement '.BlueprintDeriver::class.' to be used as a "derive" reference.');
});

it('throws a result with every error formatted', function (): void {
    $result = BlueprintValidator::withDefaults()->validate(Blueprint::define(Name::make('subjects'), Title::make('Subjects'), Table::make('App\\Missing\\Table')));

    expect(fn () => $result->throwIfInvalid())->toThrow(
        InvalidBlueprintException::class,
        "Blueprint \"subjects\" failed validation with 1 error(s).\n\nApp\\Missing\\Table does not exist or cannot be autoloaded.\nDefinition: table\nReceived: App\\Missing\\Table",
    );
});

it('locks its rule set on first validation', function (): void {
    $rules = ReferenceRules::defaults();
    (new BlueprintValidator($rules))->validate(Blueprint::define(Name::make('subjects')));

    expect($rules->isLocked())->toBeTrue()
        ->and(fn () => $rules->add(new ImplementsContract('table', TableContract::class)))->toThrow(LogicException::class, 'Cannot add '.ImplementsContract::class);
});
