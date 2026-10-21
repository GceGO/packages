<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Exceptions\InvalidBlueprintException;
use Gcego\Blueprints\Tests\Fixtures\ExampleAction;
use Gcego\Blueprints\Tests\Fixtures\ExampleColumn;
use Gcego\Blueprints\Tests\Fixtures\ExampleForm;
use Gcego\Blueprints\Tests\Fixtures\ExampleStyle;
use Gcego\Blueprints\Tests\Fixtures\ExampleTable;
use Gcego\Blueprints\Tests\Fixtures\ResourceBlueprint;
use Gcego\Components\Exceptions\DuplicatePropertyException;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Exceptions\InvalidPropertyException;
use Gcego\Components\Properties\Property;
use Gcego\Components\Validation\ErrorCode;

it('accepts the minimal blueprint', function (): void {
    $blueprint = new Blueprint(new Property(Blueprint::Name, 'subjects'));

    expect($blueprint->validate()->isValid())->toBeTrue()
        ->and($blueprint->name())->toBe('subjects')
        ->and($blueprint->title())->toBe('Subjects')
        ->and($blueprint->slug())->toBe('subjects')
        ->and($blueprint->type())->toBe('blueprint')
        ->and($blueprint->parts()->isEmpty())->toBeTrue()
        ->and($blueprint->roles())->toBe([]);
});

it('supports the advanced Property syntax', function (): void {
    $blueprint = new Blueprint(
        new Property(Blueprint::Name, 'subjects'),
        new Property(Blueprint::Title, 'Subject Management'),
        new Property(Blueprint::Description, 'Manage academic subjects'),
        new Property(Blueprint::Icon, 'academic-cap'),
        new Property(Blueprint::Slug, 'subject-management'),
    );

    expect($blueprint->metadata()->toArray())->toBe([
        'name' => 'subjects',
        'title' => 'Subject Management',
        'slug' => 'subject-management',
        'description' => 'Manage academic subjects',
        'icon' => 'academic-cap',
        'titleDerived' => false,
        'slugDerived' => false,
    ])->and($blueprint->validate()->isValid())->toBeTrue();
});

it('supports the name shorthand and treats only the first string as the name', function (): void {
    $short = new Blueprint('subjects', new ExampleTable('table'));
    $long = new Blueprint(new Property(Blueprint::Name, 'subjects'), new ExampleTable('table'));

    expect($short->normalize())->toBe($long->normalize())
        ->and(fn () => new Blueprint('subjects', 'Subject Management'))
        ->toThrow(InvalidConfigurationException::class, 'Blueprint accepts 1 positional shorthand value(s): name');
});

it('derives title and slug from the name', function (string $name, string $title, string $slug): void {
    $metadata = (new Blueprint($name))->metadata();

    expect($metadata->title)->toBe($title)
        ->and($metadata->slug)->toBe($slug)
        ->and($metadata->titleDerived)->toBeTrue()
        ->and($metadata->slugDerived)->toBeTrue();
})->with([
    ['subjects', 'Subjects', 'subjects'],
    ['academic-subjects', 'Academic Subjects', 'academic-subjects'],
    ['exam_papers', 'Exam Papers', 'exam-papers'],
    ['gce2024', 'Gce2024', 'gce2024'],
]);

it('requires a name', function (): void {
    $error = (new Blueprint)->validate()->first();

    expect($error?->code)->toBe(ErrorCode::MissingProperty)
        ->and($error?->subject)->toBe(Blueprint::Name)
        ->and((new Blueprint)->metadata()->title)->toBeNull()
        ->and(fn () => (new Blueprint)->identity())->toThrow(InvalidBlueprintException::class, 'has no valid name');
});

it('rejects invalid names, including class strings', function (string $name): void {
    $error = (new Blueprint($name))->validate()->first();

    expect($error?->code)->toBe(ErrorCode::InvalidValue)
        ->and($error?->expected)->toContain('a lowercase name such as "subjects"')
        ->and((new Blueprint($name))->metadata()->slug)->toBeNull();
})->with(['Subjects', 'subject management', '1subjects', 'subjects-', 'App\\Models\\Subject', 'a.b', '']);

it('rejects invalid metadata values', function (string $key, mixed $value, ErrorCode $code): void {
    expect((new Blueprint('subjects', new Property($key, $value)))->validate()->first()?->code)->toBe($code);
})->with([
    [Blueprint::Title, '   ', ErrorCode::InvalidValue],
    [Blueprint::Title, 42, ErrorCode::InvalidType],
    [Blueprint::Slug, 'Not A Slug', ErrorCode::InvalidValue],
    [Blueprint::Icon, 'bad icon!', ErrorCode::InvalidValue],
    [Blueprint::Description, ['x'], ErrorCode::InvalidType],
    [Blueprint::Title, null, ErrorCode::InvalidType],
]);

it('rejects unknown metadata such as label or id', function (string $key): void {
    expect((new Blueprint('subjects', new Property($key, 'x')))->validate()->first()?->code)->toBe(ErrorCode::UnknownProperty);
})->with(['label', 'id', 'model', 'route']);

it('rejects unsupported constructor items and duplicate properties', function (): void {
    expect(fn () => new Blueprint('subjects', new ExampleTable('t'), 'late'))->toThrow(InvalidConfigurationException::class, 'Positional shorthand values must precede')
        ->and(fn () => new Blueprint('subjects', new Property(Blueprint::Name, 'other')))->toThrow(DuplicatePropertyException::class)
        ->and(fn () => new Blueprint(new stdClass))->toThrow(InvalidPropertyException::class, 'Unsupported value for property "name"');
});

it('composes nested components through the component engine', function (): void {
    $blueprint = new Blueprint(
        'subjects',
        new ExampleTable('table', new ExampleColumn('name', 'Subject Name'), new ExampleColumn('code', 'Code')),
        new ExampleForm('form', new ExampleColumn('name')),
        new ExampleAction('create'),
    );

    expect($blueprint->validate()->isValid())->toBeTrue()
        ->and($blueprint->tree()->count())->toBe(7)
        ->and($blueprint->tree()->find('subjects.table.code'))->not->toBeNull();
});

it('keeps configuration nodes apart from parts and children', function (): void {
    $style = new ExampleStyle(['rounded-2xl', 'border']);
    $blueprint = new Blueprint('subjects', $style, new ExampleTable('table'));

    expect($blueprint->childCount())->toBe(1)
        ->and($blueprint->parts()->count())->toBe(1)
        ->and($blueprint->configurations()->first(ExampleStyle::class))->toBe($style)
        ->and($blueprint->validate()->isValid())->toBeTrue();
});

it('gives each subclass its own type', function (): void {
    expect((new ResourceBlueprint('subjects'))->type())->toBe(ResourceBlueprint::class);
});

it('exposes a deterministic identity', function (): void {
    $identity = (new Blueprint('exam_papers'))->identity();

    expect($identity->toArray())->toBe(['name' => 'exam_papers', 'slug' => 'exam-papers', 'class' => Blueprint::class, 'type' => 'blueprint'])
        ->and($identity->key())->toBe('exam_papers')
        ->and($identity->equals((new Blueprint('exam_papers'))->identity()))->toBeTrue()
        ->and($identity->equals((new Blueprint('exam_papers', new Property(Blueprint::Slug, 'papers')))->identity()))->toBeFalse();
});
