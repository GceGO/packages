<?php

declare(strict_types=1);

use App\GCEGo\Modules\Academics\Subjects\Actions\DeleteSubject;
use App\GCEGo\Modules\Academics\Subjects\Actions\ExportSubjects;
use App\GCEGo\Modules\Academics\Subjects\Forms\SubjectForm;
use App\GCEGo\Modules\Academics\Subjects\Infolists\SubjectInfolist;
use App\GCEGo\Modules\Academics\Subjects\Pages\CreateSubject;
use App\GCEGo\Modules\Academics\Subjects\Pages\EditSubject;
use App\GCEGo\Modules\Academics\Subjects\Pages\ListSubjects;
use App\GCEGo\Modules\Academics\Subjects\Pages\ViewSubject;
use App\GCEGo\Modules\Academics\Subjects\Relations\SyllabiRelation;
use App\GCEGo\Modules\Academics\Subjects\Relations\TopicsRelation;
use App\GCEGo\Modules\Academics\Subjects\Subjects;
use App\GCEGo\Modules\Academics\Subjects\Tables\SubjectsTable;
use App\GCEGo\Modules\Academics\Subjects\Widgets\SubjectsOverview;
use App\GCEGo\Modules\Academics\Subjects\Widgets\SubjectsStatistics;
use App\Models\Subject;
use Gcego\Blueprints\Blueprint;
use Gcego\Registry\Discovery\DefinitionDiscovery;
use Gcego\Registry\Registry;

/*
 * The documented Subjects module, discovered from tests/Fixtures/App/GCEGo/Modules.
 * Subjects.php is a class entrypoint (it declares the class and returns nothing),
 * and the same file is reachable through Composer's autoloader (App\ namespace).
 */
function discoverSubjects(): Registry
{
    test()->rebuildApplicationPublic(['gcego-blueprints.discovery.paths' => [dirname(__DIR__).'/Fixtures/App/GCEGo/Modules']]);

    return app(Registry::class);
}

it('discovers the Subjects module and exposes its complete blueprint', function (): void {
    $modules = dirname(__DIR__).'/Fixtures/App/GCEGo/Modules';
    $registry = discoverSubjects();

    $blueprint = $registry->get('blueprint:subjects', Blueprint::class);

    expect($registry->names())->toBe(['subjects'])
        ->and(array_map(static fn ($file): string => $file->relativePath, app(DefinitionDiscovery::class)->discover()->files))->toBe(['Academics/Subjects/Subjects.php'])
        ->and($registry->descriptor('blueprint:subjects')->source->location)->toBe(realpath($modules.'/Academics/Subjects/Subjects.php'))
        ->and($blueprint->toArray())->toBe([
            'name' => 'subjects',
            'title' => 'Subject Management',
            'slug' => 'subjects',
            'definitions' => [
                'name' => 'subjects',
                'title' => 'Subject Management',
                'description' => 'Manage academic subjects',
                'model' => Subject::class,
                'table' => SubjectsTable::class,
                'form' => SubjectForm::class,
                'infolist' => SubjectInfolist::class,
                'pages' => [ListSubjects::class, CreateSubject::class, EditSubject::class, ViewSubject::class],
                'actions' => [DeleteSubject::class, ExportSubjects::class],
                'relations' => [TopicsRelation::class, SyllabiRelation::class],
                'widgets' => [SubjectsOverview::class, SubjectsStatistics::class],
            ],
        ])
        ->and($blueprint->pages())->toBe([ListSubjects::class, CreateSubject::class, EditSubject::class, ViewSubject::class])
        ->and($blueprint->table())->toBe(SubjectsTable::class);
});

it('discovers the Subjects module after Composer autoloaded its class, and again after refresh', function (): void {
    $direct = (new Subjects)->blueprint();
    $registry = discoverSubjects();

    $discovered = $registry->get('blueprint:subjects', Blueprint::class);
    $registry->refresh();

    expect($discovered->toArray())->toBe($direct->toArray())
        ->and($registry->get('blueprint:subjects', Blueprint::class)->toArray())->toBe($direct->toArray());
});
