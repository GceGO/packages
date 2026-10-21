<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Blueprints\Definitions\Actions;
use Gcego\Blueprints\Definitions\BulkActions;
use Gcego\Blueprints\Definitions\Description;
use Gcego\Blueprints\Definitions\Form;
use Gcego\Blueprints\Definitions\Icon;
use Gcego\Blueprints\Definitions\Infolist;
use Gcego\Blueprints\Definitions\Model;
use Gcego\Blueprints\Definitions\Name;
use Gcego\Blueprints\Definitions\Pages;
use Gcego\Blueprints\Definitions\Policy;
use Gcego\Blueprints\Definitions\Relations;
use Gcego\Blueprints\Definitions\Table;
use Gcego\Blueprints\Definitions\Title;
use Gcego\Blueprints\Definitions\Widgets;
use Gcego\Blueprints\Exceptions\InvalidBlueprintException;
use Gcego\Blueprints\Tests\Fixtures\Extensions\Navigation;
use Gcego\Blueprints\Tests\Fixtures\ImpostorTable;
use Gcego\Blueprints\Validation\BlueprintErrorCode;

function definedOrFailure(Closure $define): InvalidBlueprintException
{
    try {
        $define();
    } catch (InvalidBlueprintException $exception) {
        return $exception;
    }

    throw new RuntimeException('Blueprint::define() did not throw.');
}

it('exposes every definition through typed accessors', function (): void {
    $blueprint = Blueprint::define(
        Name::make('subjects'),
        Title::make('Subject Management'),
        Description::make('Manage academic subjects'),
        Model::make('App\\Models\\Subject'),
        Table::make('App\\Subjects\\Tables\\SubjectsTable'),
        Form::make('App\\Subjects\\Forms\\SubjectForm'),
        Infolist::make('App\\Subjects\\Infolists\\SubjectInfolist'),
        Pages::make(['App\\Subjects\\Pages\\ListSubjects', 'App\\Subjects\\Pages\\CreateSubject']),
        Actions::make(['App\\Subjects\\Actions\\DeleteSubject']),
        Relations::make(['App\\Subjects\\Relations\\TopicsRelation']),
        Widgets::make(['App\\Subjects\\Widgets\\SubjectsOverview']),
    );

    expect($blueprint)->toBeInstanceOf(BlueprintInterface::class)
        ->and($blueprint->name())->toBe('subjects')
        ->and($blueprint->title())->toBe('Subject Management')
        ->and($blueprint->description())->toBe('Manage academic subjects')
        ->and($blueprint->slug())->toBe('subjects')
        ->and($blueprint->model())->toBe('App\\Models\\Subject')
        ->and($blueprint->table())->toBe('App\\Subjects\\Tables\\SubjectsTable')
        ->and($blueprint->form())->toBe('App\\Subjects\\Forms\\SubjectForm')
        ->and($blueprint->infolist())->toBe('App\\Subjects\\Infolists\\SubjectInfolist')
        ->and($blueprint->pages())->toBe(['App\\Subjects\\Pages\\ListSubjects', 'App\\Subjects\\Pages\\CreateSubject'])
        ->and($blueprint->actions())->toBe(['App\\Subjects\\Actions\\DeleteSubject'])
        ->and($blueprint->relations())->toBe(['App\\Subjects\\Relations\\TopicsRelation'])
        ->and($blueprint->widgets())->toBe(['App\\Subjects\\Widgets\\SubjectsOverview'])
        ->and($blueprint->derivers())->toBe([]);
});

it('exposes the icon, policy and bulk actions', function (): void {
    $blueprint = Blueprint::define(
        Name::make('subjects'),
        Icon::make('academic-cap'),
        Policy::make('App\\Policies\\SubjectPolicy'),
        BulkActions::make(['App\\Subjects\\Actions\\DeleteSubjects', 'App\\Subjects\\Actions\\ArchiveSubjects']),
    );

    expect($blueprint->icon())->toBe('academic-cap')
        ->and($blueprint->metadata()->icon)->toBe('academic-cap')
        ->and($blueprint->policy())->toBe('App\\Policies\\SubjectPolicy')
        ->and($blueprint->bulkActions())->toBe(['App\\Subjects\\Actions\\DeleteSubjects', 'App\\Subjects\\Actions\\ArchiveSubjects'])
        ->and($blueprint->actions())->toBe([]);
});

it('returns null and empty lists for absent definitions', function (): void {
    $blueprint = Blueprint::define(Name::make('dashboard'));

    expect($blueprint->description())->toBeNull()
        ->and($blueprint->icon())->toBeNull()
        ->and($blueprint->policy())->toBeNull()
        ->and($blueprint->bulkActions())->toBe([])
        ->and($blueprint->model())->toBeNull()
        ->and($blueprint->table())->toBeNull()
        ->and($blueprint->form())->toBeNull()
        ->and($blueprint->infolist())->toBeNull()
        ->and($blueprint->pages())->toBe([])
        ->and($blueprint->actions())->toBe([])
        ->and($blueprint->relations())->toBe([])
        ->and($blueprint->widgets())->toBe([])
        ->and($blueprint->has('table'))->toBeFalse()
        ->and($blueprint->definition('table'))->toBeNull()
        ->and($blueprint->get('table'))->toBeNull();
});

it('derives the title and slug from the name when no title is declared', function (): void {
    $blueprint = Blueprint::define(Name::make('academic_subjects'));

    expect($blueprint->title())->toBe('Academic Subjects')
        ->and($blueprint->slug())->toBe('academic-subjects')
        ->and($blueprint->metadata()->titleDerived)->toBeTrue();
});

it('keeps definitions in declaration order', function (): void {
    $blueprint = Blueprint::define(
        Widgets::make(['App\\Widgets\\Overview']),
        Name::make('subjects'),
        Table::make('App\\Tables\\SubjectsTable'),
        Title::make('Subjects'),
    );

    expect($blueprint->keys())->toBe(['widgets', 'name', 'table', 'title'])
        ->and(array_keys($blueprint->references()))->toBe(['widgets', 'table'])
        ->and(array_map(static fn ($definition): string => $definition::class, $blueprint->definitions()))
        ->toBe([Widgets::class, Name::class, Table::class, Title::class]);
});

it('accepts definitions spread from an array', function (): void {
    $definitions = ['identity' => Name::make('subjects'), 'list' => Table::make('App\\Tables\\SubjectsTable')];

    expect(Blueprint::define(...$definitions)->keys())->toBe(['name', 'table']);
});

it('normalizes to an array of definition values', function (): void {
    $blueprint = Blueprint::define(
        Name::make('subjects'),
        Table::make('\\App\\Tables\\SubjectsTable'),
        Pages::make(['App\\Pages\\ListSubjects']),
    );

    expect($blueprint->toArray())->toBe([
        'name' => 'subjects',
        'title' => 'Subjects',
        'slug' => 'subjects',
        'definitions' => [
            'name' => 'subjects',
            'table' => 'App\\Tables\\SubjectsTable',
            'pages' => ['App\\Pages\\ListSubjects'],
        ],
    ]);
});

it('reports the identity registries key blueprints by', function (): void {
    expect(Blueprint::define(Name::make('exam_papers'))->identity()->toArray())->toBe([
        'name' => 'exam_papers',
        'slug' => 'exam-papers',
        'class' => Blueprint::class,
        'type' => 'blueprint',
    ]);
});

it('finds a definition by class', function (): void {
    $table = Table::make('App\\Tables\\SubjectsTable');
    $blueprint = Blueprint::define(Name::make('subjects'), $table);

    expect($blueprint->find(Table::class))->toBe($table)
        ->and($blueprint->find(Form::class))->toBeNull();
});

it('rejects a blueprint without a name', function (): void {
    $failure = definedOrFailure(fn () => Blueprint::define(Table::make('App\\Tables\\SubjectsTable')));

    expect($failure->getMessage())->toContain('Blueprint "(unnamed)" failed validation with 1 error(s).')
        ->toContain('The required "name" definition is missing.')
        ->toContain("Add Name::make('subjects').")
        ->and($failure->result()?->first()?->code)->toBe(BlueprintErrorCode::MissingRequiredDefinition->value);
});

it('rejects a blueprint without definitions', function (): void {
    Blueprint::define();
})->throws(InvalidBlueprintException::class, 'The required "name" definition is missing.');

it('rejects a single-reference definition declared twice instead of overwriting it', function (): void {
    $failure = definedOrFailure(fn () => Blueprint::define(
        Name::make('subjects'),
        Table::make('App\\Tables\\SubjectsTable'),
        Table::make('App\\Tables\\ArchivedSubjectsTable'),
    ));

    expect($failure->getMessage())->toContain('The "table" definition is declared twice (arguments 2 and 3); each definition may appear once.')
        ->and($failure->result()?->errorsWithCode(BlueprintErrorCode::DuplicateDefinition))->toHaveCount(1);
});

it('rejects a collection definition declared twice and suggests merging the lists', function (): void {
    Blueprint::define(
        Name::make('subjects'),
        Pages::make(['App\\Pages\\ListSubjects', 'App\\Pages\\EditSubject']),
        Pages::make(['App\\Pages\\ViewSubject']),
    );
})->throws(InvalidBlueprintException::class, 'Merge the lists into one ::make([...]) call.');

it('rejects a second name', function (): void {
    Blueprint::define(Name::make('subjects'), Name::make('courses'));
})->throws(InvalidBlueprintException::class, 'The "name" definition is declared twice');

it('rejects a definition type that is not in the catalog', function (): void {
    $failure = definedOrFailure(fn () => Blueprint::define(Name::make('subjects'), Navigation::make('Academics')));

    expect($failure->getMessage())->toContain('Argument 2 ('.Navigation::class.') declares the unsupported definition key "navigation".')
        ->toContain('Register extension definitions with DefinitionCatalog::register()')
        ->and($failure->result()?->first()?->code)->toBe(BlueprintErrorCode::UnsupportedDefinition->value);
});

it('rejects a class that claims a built-in key', function (): void {
    $failure = definedOrFailure(fn () => Blueprint::define(Name::make('subjects'), new ImpostorTable));

    expect($failure->result()?->first()?->toArray())->toMatchArray([
        'code' => 'blueprint.unsupported_definition',
        'key' => 'table',
        'expected' => Table::class,
        'received' => ImpostorTable::class,
    ]);
});

it('reports every problem of a definition list at once', function (): void {
    $failure = definedOrFailure(fn () => Blueprint::define(
        Table::make('App\\Tables\\SubjectsTable'),
        Table::make('App\\Tables\\OtherTable'),
        Navigation::make('Academics'),
    ));

    expect(array_map(static fn ($error): string => $error->code, $failure->result()?->errors() ?? []))->toBe([
        'blueprint.duplicate_definition',
        'blueprint.unsupported_definition',
        'blueprint.missing_required_definition',
    ]);
});

it('cannot be constructed directly', function (): void {
    expect((new ReflectionClass(Blueprint::class))->getConstructor()?->isPrivate())->toBeTrue();
});
