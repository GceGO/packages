<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Data\Source;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Modules\Exceptions\ModuleNotFoundException;
use Gcego\Modules\Module;
use Gcego\Modules\Relation;
use Gcego\Modules\Relationships\Attach;
use Gcego\Modules\Resource;
use Gcego\Modules\Tests\Fixtures\Models\Note;
use Gcego\Modules\Tests\Fixtures\Models\Subject;
use Gcego\Modules\Tests\Fixtures\Models\Topic;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;

it('reports a healthy module with its resources and pages', function (): void {
    $report = modules()->inspect('academics');

    expect($report->isHealthy())->toBeTrue()
        ->and($report->resources['subjects']['operations'])->toBe(['index', 'create', 'view', 'edit'])
        ->and($report->resources['subjects']['relations'])->toBe(['topics', 'tags'])
        ->and($report->pages)->toContain('page:academics-subjects-tags', 'page:academics-overview')
        ->and($report->toArray()['healthy'])->toBeTrue();
});

it('warns about resources that expose nothing and models without policies', function (): void {
    $report = modules()->inspect('notes');

    expect($report->isHealthy())->toBeTrue()
        ->and($report->checks())->toContain('resource.empty', 'authorization.policy');
});

it('finds relationships that do not exist and Attach on the wrong relationship type', function (): void {
    modules()->register(new Module(
        'broken',
        new Resource(
            new Source(Subject::class),
            new Property(Resource::Slug, 'broken-subjects'),
            new Form(new TextInput('name', 'Name')),
            new Relation('chapters', new Property(Relation::Model, Topic::class), new Table(new Text('name', 'Name'))),
            new Relation('topics', new Property(Relation::Model, Topic::class), new Table(new Text('name', 'Name')), new Attach),
            new Relation('notARelationship', new Property(Relation::Model, Note::class), new Table(new Text('body', 'Body'))),
        ),
    ));

    $report = modules()->inspect('broken');

    expect($report->isHealthy())->toBeFalse()
        ->and($report->checks())->toContain('relation.missing', 'relation.attach', 'relation.invalid', 'resource.form');
});

it('reports invalid modules without throwing and unknown modules as not found', function (): void {
    expect(modules()->inspect('missing')->checks())->toBe(['module.invalid'])
        ->and(fn () => modules()->module('missing'))->toThrow(ModuleNotFoundException::class)
        ->and(modules()->inspectAll())->toHaveCount(2);
});

it('inspects without querying', function (): void {
    expect(queriesDuring(fn () => modules()->inspectAll()))->toBe([]);
});
