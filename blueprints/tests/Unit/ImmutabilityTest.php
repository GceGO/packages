<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Tests\Fixtures\ExampleAction;
use Gcego\Blueprints\Tests\Fixtures\ExampleForm;
use Gcego\Blueprints\Tests\Fixtures\ExampleSource;
use Gcego\Blueprints\Tests\Fixtures\ExampleTable;
use Gcego\Blueprints\Tests\Fixtures\FakeModel;
use Gcego\Blueprints\Tests\Fixtures\ResourceBlueprint;
use Gcego\Components\Property;

it('replaces single-role parts in place and keeps everything else', function (): void {
    $original = new Blueprint('subjects', new ExampleForm('form'), new ExampleTable('old'), new ExampleAction('create'));
    $customized = $original->withPart(new ExampleTable('new'));

    expect(array_map(fn ($part): ?string => $part->name(), $customized->parts()->all()))->toBe(['form', 'new', 'create'])
        ->and(array_map(fn ($part): ?string => $part->name(), $original->parts()->all()))->toBe(['form', 'old', 'create'])
        ->and($customized->title())->toBe('Subjects');
});

it('collapses duplicate single-role parts when replacing', function (): void {
    $customized = (new Blueprint('subjects', new ExampleTable('a'), new ExampleTable('b')))->withPart(new ExampleTable('c'));

    expect($customized->parts('table')->count())->toBe(1)
        ->and($customized->validate()->isValid())->toBeTrue();
});

it('appends multiple-role parts and missing single roles', function (): void {
    $customized = (new Blueprint('subjects', new ExampleAction('create')))
        ->withPart(new ExampleAction('edit'))
        ->withPart(new ExampleTable('table'));

    expect($customized->parts()->paths())->toBe(['subjects.create', 'subjects.edit', 'subjects.table']);
});

it('respects policy-forced single roles', function (): void {
    $customized = (new ResourceBlueprint('subjects', new ExampleSource(FakeModel::class), new ExampleTable('table')))
        ->withPart(new ExampleSource(FakeModel::class));

    expect($customized->parts('source')->count())->toBe(1);
});

it('removes roles', function (): void {
    $customized = (new Blueprint('subjects', new ExampleTable('table'), new ExampleAction('a'), new ExampleAction('b')))->withoutRole('action');

    expect($customized->roles())->toBe(['table']);
});

it('preserves the concrete subtype', function (): void {
    $blueprint = new ResourceBlueprint('subjects', new ExampleTable('table'));

    expect($blueprint->withPart(new ExampleForm('form')))->toBeInstanceOf(ResourceBlueprint::class)
        ->and($blueprint->withoutRole('form'))->toBeInstanceOf(ResourceBlueprint::class)
        ->and($blueprint->withProperty(new Property(Blueprint::Title, 'Academic Subjects')))->toBeInstanceOf(ResourceBlueprint::class);
});

it('recomputes composition and messages for copies', function (): void {
    $original = new Blueprint('subjects', new ExampleTable('a'));
    $original->composition();

    $duplicated = $original->withAppendedChildren(new ExampleTable('b'));
    $renamed = $duplicated->withProperty(new Property(Blueprint::Name, 'courses'));

    expect($original->composition()->hasIssues())->toBeFalse()
        ->and($duplicated->composition()->hasIssues())->toBeTrue()
        ->and($renamed->composition()->messages()[0])->toStartWith('Blueprint "courses"');
});

it('changes metadata through the component copy API', function (): void {
    $original = new Blueprint('subjects');
    $retitled = $original->withProperty(new Property(Blueprint::Title, 'Academic Subjects'));

    expect($retitled->title())->toBe('Academic Subjects')
        ->and($original->title())->toBe('Subjects')
        ->and($retitled->metadata()->titleDerived)->toBeFalse();
});

it('allows reusing one part instance in several blueprints', function (): void {
    $table = new ExampleTable('table');
    $a = new Blueprint('subjects', $table);
    $b = new Blueprint('courses', $table);

    expect($a->part('table'))->toBe($b->part('table'))
        ->and($a->parts()->paths())->toBe(['subjects.table'])
        ->and($b->parts()->paths())->toBe(['courses.table']);
});
