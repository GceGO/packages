<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Composition\BlueprintComposer;
use Gcego\Blueprints\Composition\ClassifiedPart;
use Gcego\Blueprints\Composition\CompositionPolicy;
use Gcego\Blueprints\Exceptions\BlueprintCompositionException;
use Gcego\Blueprints\Exceptions\InvalidBlueprintPartException;
use Gcego\Blueprints\Tests\Fixtures\ConflictingTable;
use Gcego\Blueprints\Tests\Fixtures\ExampleAction;
use Gcego\Blueprints\Tests\Fixtures\ExampleColumn;
use Gcego\Blueprints\Tests\Fixtures\ExampleForm;
use Gcego\Blueprints\Tests\Fixtures\ExampleSection;
use Gcego\Blueprints\Tests\Fixtures\ExampleSource;
use Gcego\Blueprints\Tests\Fixtures\ExampleTable;
use Gcego\Blueprints\Tests\Fixtures\ExampleWidget;
use Gcego\Blueprints\Tests\Fixtures\FakeModel;
use Gcego\Blueprints\Tests\Fixtures\InvalidRolePart;
use Gcego\Blueprints\Tests\Fixtures\ReportChart;
use Gcego\Blueprints\Validation\BlueprintErrorCode;

it('classifies one part', function (): void {
    $composition = (new Blueprint('subjects', new ExampleTable('table')))->composition();

    expect($composition->roles())->toBe(['table'])
        ->and($composition->has('table'))->toBeTrue()
        ->and($composition->role('table')?->multiple)->toBeFalse()
        ->and($composition->role('form'))->toBeNull()
        ->and($composition->hasIssues())->toBeFalse();
});

it('classifies many parts in document order', function (): void {
    $composition = (new Blueprint(
        'subjects',
        new ExampleSource(FakeModel::class),
        new ExampleTable('table'),
        new ExampleAction('create'),
        new ExampleForm('form'),
        new ExampleAction('edit'),
    ))->composition();

    expect($composition->roles())->toBe(['source', 'table', 'action', 'form'])
        ->and(array_map(fn (ClassifiedPart $entry): string => $entry->path->toString(), $composition->parts()->entries()))
        ->toBe(['subjects.#0', 'subjects.table', 'subjects.create', 'subjects.form', 'subjects.edit'])
        ->and($composition->ofRole('action')->paths())->toBe(['subjects.create', 'subjects.edit'])
        ->and($composition->role('action')?->multiple)->toBeTrue()
        ->and($composition->hasIssues())->toBeFalse();
});

it('does not treat a part\'s private children as blueprint parts', function (): void {
    $blueprint = new Blueprint('subjects', new ExampleTable('table', new ExampleColumn('name'), new ExampleAction('row-delete')));

    expect($blueprint->roles())->toBe(['table'])
        ->and($blueprint->hasPart('action'))->toBeFalse();
});

it('collects nested parts through part containers', function (): void {
    $blueprint = new Blueprint(
        'dashboard',
        new ExampleSection('overview', new ExampleWidget('enrolments'), new ExampleSection('inner', new ExampleWidget('results'))),
        new ExampleWidget('alerts'),
    );

    expect($blueprint->roles())->toBe(['layout', 'widget'])
        ->and($blueprint->parts('widget')->paths())->toBe(['dashboard.overview.enrolments', 'dashboard.overview.inner.results', 'dashboard.alerts'])
        ->and($blueprint->parts('layout')->paths())->toBe(['dashboard.overview', 'dashboard.overview.inner'])
        ->and($blueprint->validate()->isValid())->toBeTrue();
});

it('accepts roles no GCEGO package defines', function (): void {
    $blueprint = new Blueprint('reports', new ReportChart('a'), new ReportChart('b'));

    expect($blueprint->parts('reports.chart')->count())->toBe(2)
        ->and($blueprint->validate()->isValid())->toBeTrue();
});

it('reports duplicate single-role parts', function (): void {
    $composition = (new Blueprint('subjects', new ExampleTable('first'), new ExampleTable('second'), new ExampleTable('third')))->composition();
    $issues = $composition->issues();

    expect($issues)->toHaveCount(2)
        ->and($issues[0]->code)->toBe(BlueprintErrorCode::DuplicateSingleton->value)
        ->and($issues[0]->path)->toBe('subjects.second')
        ->and($issues[0]->role)->toBe('table')
        ->and($issues[0]->message)->toBe('Blueprint "subjects" already has a "table" part at subjects.first; the role allows only one.')
        ->and($issues[1]->path)->toBe('subjects.third')
        ->and($issues[0]->received)->toBe('3 "table" parts');
});

it('reports parts that disagree about cardinality', function (): void {
    $issues = (new Blueprint('subjects', new ExampleTable('first'), new ConflictingTable('second')))->composition()->issues();

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->code)->toBe(BlueprintErrorCode::RoleConflict->value)
        ->and($issues[0]->received)->toBe('subjects.first (single), subjects.second (multiple)');
});

it('reports invalid roles without crashing composition', function (): void {
    $composition = (new Blueprint('subjects', new InvalidRolePart('bad'), new ExampleTable('table')))->composition();

    expect($composition->roles())->toBe(['table'])
        ->and($composition->issues()[0]->code)->toBe(BlueprintErrorCode::InvalidRole->value)
        ->and($composition->issues()[0]->path)->toBe('subjects.bad')
        ->and($composition->issues()[0]->hint)->toContain('Invalid blueprint role "Not A Role"');
});

it('validates role identifiers', function (): void {
    expect(BlueprintRole::single('table')->toArray())->toBe(['name' => 'table', 'multiple' => false])
        ->and(BlueprintRole::multiple('reports.chart')->multiple)->toBeTrue()
        ->and(BlueprintRole::isValidName('Table'))->toBeFalse()
        ->and(fn () => new BlueprintRole(''))->toThrow(InvalidBlueprintPartException::class);
});

it('validates policies', function (): void {
    $policy = (new CompositionPolicy)->withRequiredRoles('table')->withSingleRoles('source')->withAllowedRoles(['table', 'source']);

    expect($policy->toArray())->toBe(['requiredRoles' => ['table'], 'allowedRoles' => ['table', 'source'], 'singleRoles' => ['source']])
        ->and($policy->isRequired('table'))->toBeTrue()
        ->and($policy->isAllowed('form'))->toBeFalse()
        ->and((new CompositionPolicy)->isAllowed('anything'))->toBeTrue()
        ->and($policy->forcesSingle('source'))->toBeTrue()
        ->and(fn () => new CompositionPolicy(['Bad']))->toThrow(BlueprintCompositionException::class, 'required roles must be role identifiers')
        ->and(fn () => new CompositionPolicy(['form'], ['table']))->toThrow(BlueprintCompositionException::class, 'required role(s) form are not in the allowed roles');
});

it('computes composition once per instance', function (): void {
    $blueprint = new Blueprint('subjects', new ExampleTable('table'));

    expect($blueprint->composition())->toBe($blueprint->composition());
});

it('composes through the stateless composer as well', function (): void {
    $blueprint = new Blueprint('subjects', new ExampleTable('table'));

    expect((new BlueprintComposer)->compose($blueprint)->roles())->toBe($blueprint->roles());
});
