<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Exceptions\InvalidBlueprintException;
use Gcego\Blueprints\Tests\Fixtures\ExampleAction;
use Gcego\Blueprints\Tests\Fixtures\ExampleColumn;
use Gcego\Blueprints\Tests\Fixtures\ExampleForm;
use Gcego\Blueprints\Tests\Fixtures\ExampleSource;
use Gcego\Blueprints\Tests\Fixtures\ExampleTable;
use Gcego\Blueprints\Tests\Fixtures\ExampleWidget;
use Gcego\Blueprints\Tests\Fixtures\FakeModel;
use Gcego\Blueprints\Tests\Fixtures\ResourceBlueprint;
use Gcego\Blueprints\Validation\BlueprintErrorCode;
use Gcego\Blueprints\Validation\BlueprintValidationError;
use Gcego\Blueprints\Validation\BlueprintValidator;
use Gcego\Components\Component;
use Gcego\Components\Property;

it('accepts valid blueprints of any shape', function (Blueprint $blueprint): void {
    expect((new BlueprintValidator)->validate($blueprint)->isValid())->toBeTrue();
})->with([
    'listing only' => [fn () => new Blueprint('subjects', new ExampleTable('table'))],
    'form only' => [fn () => new Blueprint('enrolment', new ExampleForm('form'))],
    'dashboard' => [fn () => new Blueprint('dashboard', new ExampleWidget('a'), new ExampleWidget('b'))],
    'empty' => [fn () => new Blueprint('placeholder')],
]);

it('reports invalid metadata as component errors', function (): void {
    $error = (new BlueprintValidator)->validate(new Blueprint('Subjects'))->first();

    expect($error?->code)->toBe('component.invalid_value')
        ->and($error?->blueprint)->toBe('Subjects')
        ->and($error?->path)->toBe('Subjects')
        ->and($error?->componentError)->not->toBeNull()
        ->and($error?->isBlueprintError())->toBeFalse();
});

it('reports children that are not blueprint parts', function (): void {
    $error = (new BlueprintValidator)->validate(new Blueprint('subjects', new ExampleColumn('stray')))->first();

    expect($error?->code)->toBe('component.invalid_child')
        ->and($error?->path)->toBe('subjects.stray');
});

it('reports duplicate singletons with role and path', function (): void {
    $result = (new BlueprintValidator)->validate(new Blueprint('subjects', new ExampleTable('a'), new ExampleTable('b')));

    expect($result->count())->toBe(1)
        ->and($result->first()?->code)->toBe(BlueprintErrorCode::DuplicateSingleton->value)
        ->and($result->errorsForRole('table'))->toHaveCount(1)
        ->and($result->first()?->isBlueprintError())->toBeTrue();
});

it('enforces policy-required and policy-allowed roles', function (): void {
    $result = (new BlueprintValidator)->validate(new ResourceBlueprint('subjects', new ExampleWidget('chart')));

    expect(array_map(fn (BlueprintValidationError $error): string => $error->code, $result->errors()))->toBe([
        BlueprintErrorCode::RoleNotAllowed->value,
        BlueprintErrorCode::MissingRequiredRole->value,
    ])
        ->and($result->errorsWithCode(BlueprintErrorCode::MissingRequiredRole)[0]->message)->toBe('Blueprint "subjects" requires a "table" part.')
        ->and($result->errorsWithCode(BlueprintErrorCode::RoleNotAllowed)[0]->path)->toBe('subjects.chart');
});

it('lets a policy force a single role', function (): void {
    $result = (new BlueprintValidator)->validate(new ResourceBlueprint(
        'subjects',
        new ExampleSource(FakeModel::class),
        new ExampleSource(FakeModel::class),
        new ExampleTable('table'),
    ));

    expect($result->errorsWithCode(BlueprintErrorCode::DuplicateSingleton))->toHaveCount(1);
});

it('preserves nested component errors with full paths', function (): void {
    $result = (new BlueprintValidator)->validate(new Blueprint(
        'subjects',
        new ExampleTable('table', new ExampleColumn(new Property(Component::Label, 'No name'))),
    ));

    expect($result->first()?->code)->toBe('component.missing_property')
        ->and($result->first()?->path)->toBe('subjects.table.#0');
});

it('collects multiple errors of both kinds', function (): void {
    $result = (new BlueprintValidator)->validate(new Blueprint(
        'Bad Name',
        new ExampleTable('a'),
        new ExampleTable('b'),
        new ExampleAction(new Property('serchable', true)),
    ));

    expect($result->count())->toBeGreaterThanOrEqual(4)
        ->and($result->errorsWithCode('component.invalid_value'))->toHaveCount(1)
        ->and($result->errorsWithCode('component.missing_property'))->toHaveCount(1)
        ->and($result->errorsWithCode('component.unknown_property'))->toHaveCount(1)
        ->and($result->errorsWithCode(BlueprintErrorCode::DuplicateSingleton))->toHaveCount(1)
        ->and($result->toArray()[0])->toHaveKeys(['code', 'message', 'blueprint', 'path', 'role', 'expected', 'received', 'hint']);
});

it('does not report composition errors twice', function (): void {
    $result = (new BlueprintValidator)->validate(new Blueprint('subjects', new ExampleTable('a'), new ExampleTable('b')));

    expect($result->errorsWithCode('component.constraint_failed'))->toBe([]);
});

it('makes the component validate() reject the same blueprints', function (): void {
    $blueprint = new Blueprint('subjects', new ExampleTable('a'), new ExampleTable('b'));

    expect($blueprint->validate()->isValid())->toBeFalse()
        ->and($blueprint->validate()->first()?->message)->toBe('Blueprint "subjects" already has a "table" part at subjects.a; the role allows only one.');
});

it('throws a meaningful exception in strict mode', function (): void {
    try {
        (new BlueprintValidator)->assertValid(new Blueprint('subjects', new ExampleTable('a'), new ExampleTable('b')));
        $this->fail('Expected an exception.');
    } catch (InvalidBlueprintException $exception) {
        expect($exception->getMessage())->toStartWith('Blueprint "subjects" failed validation with 1 error(s).')
            ->and($exception->getMessage())->toContain("Role: table\nExpected: one \"table\" part\nReceived: 2 \"table\" parts\n\nPath: subjects.b")
            ->and($exception->result()?->count())->toBe(1);
    }

    (new BlueprintValidator)->assertValid(new Blueprint('subjects'));
});

it('formats results', function (): void {
    $result = (new BlueprintValidator)->validate(new Blueprint('subjects', new ExampleTable('a'), new ExampleTable('b')));

    expect($result->format())->toBe($result->first()?->format())
        ->and((string) $result->first())->toBe($result->first()?->format())
        ->and(iterator_to_array($result))->toBe($result->errors());
});
