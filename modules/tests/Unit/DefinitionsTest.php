<?php

declare(strict_types=1);

use Gcego\Actions\Create;
use Gcego\Actions\Delete;
use Gcego\Actions\Edit;
use Gcego\Components\Property;
use Gcego\Data\Source;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Infolists\Entries\Text as TextEntry;
use Gcego\Infolists\Infolist;
use Gcego\Luminaries\Icons\Alias;
use Gcego\Luminaries\Icons\Heroicon;
use Gcego\Modules\Compilation\ModuleCompiler;
use Gcego\Modules\Definitions\ModuleDefinition;
use Gcego\Modules\Exceptions\InvalidModuleException;
use Gcego\Modules\Module;
use Gcego\Modules\Operation;
use Gcego\Modules\Relation;
use Gcego\Modules\Resource;
use Gcego\Modules\Tests\Fixtures\Models\Subject;
use Gcego\Modules\Tests\Fixtures\Models\Tag;
use Gcego\Modules\Tests\Fixtures\Models\Topic;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Page;
use Gcego\Pages\Types\EditPage;
use Gcego\Pages\Types\IndexPage;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;

function compile(Module $module): ModuleDefinition
{
    return app(ModuleCompiler::class)->compile($module);
}

/**
 * @return array<string, Page>
 */
function derivedPages(Module $module): array
{
    $pages = [];

    foreach (app(ModuleCompiler::class)->pages($module) as $page) {
        $pages[$page->pageName()] = $page;
    }

    return $pages;
}

it('derives names from the model: a minimal resource and module', function (): void {
    $module = new Module(new Resource(Subject::class));
    $compiled = compile($module);

    expect($module->moduleName())->toBe('subjects')
        ->and($compiled->title)->toBe('Subjects')
        ->and($compiled->resources[0]->name)->toBe('subjects')
        ->and($compiled->resources[0]->label)->toBe('Subject')
        ->and($compiled->resources[0]->pluralLabel)->toBe('Subjects')
        ->and($compiled->resources[0]->pageBase)->toBe('subjects')
        ->and($compiled->resources[0]->operations)->toBe([])
        ->and(derivedPages($module))->toBe([]);
});

it('accepts property syntax and the Name/Title shorthand', function (): void {
    $module = new Module('academics', 'Academic Management', new Resource(new Property(Resource::Name, 'subjects'), new Property(Resource::Label, 'Course'), new Source(Subject::class)));

    expect($module->moduleName())->toBe('academics')
        ->and($module->title())->toBe('Academic Management')
        ->and(compile($module)->resources[0]->pluralLabel)->toBe('Courses');
});

it('exposes only declared operations', function (array $children, array $operations): void {
    $module = new Module('academics', new Resource(new Source(Subject::class), ...$children));

    expect(array_map(static fn (Operation $operation): string => $operation->value, compile($module)->resources[0]->operations))->toBe($operations);
})->with([
    'nothing' => [[], []],
    'a table' => [[new Table(new Text('name', 'Name'))], ['index']],
    'a form without actions' => [[new Form(new TextInput('name', 'Name'))], []],
    'a form with Create' => [[new Form(new TextInput('name', 'Name')), new Create], ['create']],
    'a form with Edit' => [[new Form(new TextInput('name', 'Name')), new Edit], ['edit']],
    'an infolist' => [[new Infolist(new TextEntry('name', 'Name'))], ['view']],
    'everything' => [[new Table(new Text('name', 'Name')), new Form(new TextInput('name', 'Name')), new Infolist(new TextEntry('name', 'Name')), new Create, new Edit], ['index', 'create', 'view', 'edit']],
    'restricted by Operations' => [[new Property(Resource::Operations, ['index']), new Table(new Text('name', 'Name')), new Infolist(new TextEntry('name', 'Name'))], ['index']],
    'Operations enable a form page' => [[new Property(Resource::Operations, ['edit']), new Form(new TextInput('name', 'Name'))], ['edit']],
]);

it('enables configured default operations only where their definitions exist', function (): void {
    $this->reboot(['gcego-modules.resources.default_operations' => ['create', 'edit']]);
    $module = new Module('academics', new Resource(new Source(Subject::class), new Form(new TextInput('name', 'Name')), new Table(new Text('name', 'Name'))));

    expect(array_map(static fn (Operation $operation): string => $operation->value, compile($module)->resources[0]->operations))->toBe(['index', 'create', 'edit']);
});

it('derives gcego/pages pages with names, routes and paths', function (): void {
    $pages = derivedPages(new Module(
        new Property(Module::Name, 'academics'),
        new Property(Module::Slug, 'school'),
        new Resource(new Source(Subject::class), new Table(new Text('name', 'Name')), new Form(new TextInput('name', 'Name')), new Edit, new Delete),
    ));

    expect(array_keys($pages))->toBe(['academics.subjects', 'academics.subjects.edit'])
        ->and($pages['academics.subjects'])->toBeInstanceOf(IndexPage::class)
        ->and($pages['academics.subjects']->routeName())->toBe('academics.subjects.index')
        ->and($pages['academics.subjects']->path())->toBe('school/subjects')
        ->and($pages['academics.subjects.edit'])->toBeInstanceOf(EditPage::class)
        ->and($pages['academics.subjects.edit']->path())->toBe('school/subjects/{record}/edit')
        ->and($pages['academics.subjects.edit']->validate()->isValid())->toBeTrue();
});

it('lets a custom page override a generated one and keeps other custom pages', function (): void {
    $pages = derivedPages(new Module(
        'academics',
        new Resource(
            new Source(Subject::class),
            new Table(new Text('name', 'Name')),
            new IndexPage(new Property(Page::Name, 'index'), new Property(Page::Title, 'All subjects'), new Table(new Text('code', 'Code'))),
            new Page(new Property(Page::Name, 'syllabus'), new Property(Page::Record, true), new Content('Syllabus')),
        ),
    ));

    expect(array_keys($pages))->toBe(['academics.subjects', 'academics.subjects.syllabus'])
        ->and($pages['academics.subjects']->pageTitle())->toBe('All subjects')
        ->and($pages['academics.subjects']->routeName())->toBe('academics.subjects.index')
        ->and($pages['academics.subjects']->source())->not->toBeNull()
        ->and($pages['academics.subjects.syllabus']->path())->toBe('subjects/{record}/syllabus')
        ->and($pages['academics.subjects.syllabus']->source()?->get(Source::Model))->toBe(Subject::class);
});

it('normalizes relations, reusing a resource of the module', function (): void {
    $compiled = compile(new Module(
        'academics',
        new Resource(new Source(Subject::class), new Infolist(new TextEntry('name', 'Name')), new Relation('topics', new Property(Relation::Resource, 'topics'))),
        new Resource(new Source(Topic::class), new Table(new Text('name', 'Topic')), new Form(new TextInput('name', 'Topic')), new Create),
    ));
    $relation = $compiled->resources[0]->relations[0];

    expect($relation->identity())->toBe('relation:academics.subjects.topics')
        ->and($relation->pageBase)->toBe('academics.subjects.topics')
        ->and($relation->path)->toBe('subjects/{parent}/topics')
        ->and($relation->model)->toBe(Topic::class)
        ->and($relation->pluralLabel)->toBe('Topics')
        ->and(array_map(static fn (Operation $operation): string => $operation->value, $relation->operations))->toBe(['index', 'create'])
        ->and($relation->source->configurations()->all())->not->toBe([]);
});

it('rejects invalid modules with clear messages', function (Module $module, string $message): void {
    expect(fn () => compile($module))->toThrow(InvalidModuleException::class, $message);
})->with([
    'no name' => [new Module(new Resource(Subject::class), new Resource(Topic::class)), 'needs a Name'],
    'duplicate resource' => [new Module('academics', new Resource(Subject::class), new Resource(Subject::class)), 'declared twice'],
    'unknown relation target' => [new Module('academics', new Resource(Subject::class, new Relation('topics', new Property(Relation::Resource, 'topics')))), 'does not declare'],
    'reserved resource name' => [new Module('academics', new Resource(new Property(Resource::Name, 'create'), new Source(Subject::class))), 'reserved'],
    'operation without its definition' => [new Module('academics', new Resource(Subject::class, new Property(Resource::Operations, ['index']))), 'needs a Table'],
    'two tables' => [new Module('academics', new Resource(Subject::class, new Table(new Text('name', 'Name')), new Table(new Text('code', 'Code')))), 'at most one Table'],
    'model and source differ' => [new Module('academics', new Resource(Subject::class, new Source(Tag::class))), 'differ'],
    'no source' => [new Module('academics', new Resource(new Property(Resource::Name, 'subjects'), new Table(new Text('name', 'Name')))), 'needs a Source or a Model'],
    'bad permission name' => [new Module('academics', new Resource(Subject::class, new Property(Resource::Permissions, ['index' => 'not a permission!']))), 'Permissions'],
    'empty module' => [new Module('academics'), 'at least one Resource'],
    'relation without model' => [new Module('academics', new Resource(Subject::class, new Relation('topics'))), 'needs a Model or the Resource'],
]);

it('is immutable: changes return new definitions', function (): void {
    $module = new Module('academics', new Resource(Subject::class));
    $changed = $module->withProperty(new Property(Module::Title, 'Changed'));

    expect($module->title())->toBe('Academics')
        ->and($changed->title())->toBe('Changed')
        ->and($changed)->not->toBe($module);
});

it('never queries while defining or compiling modules', function (): void {
    $queries = queriesDuring(function (): void {
        app(ModuleCompiler::class)->pages(require dirname(__DIR__).'/Fixtures/Modules/Academics/Module.php');
    });

    expect($queries)->toBe([]);
});

it('takes typed Luminaries icons for modules and resources', function (): void {
    $resource = new Resource(Subject::class, new Property(Resource::Icon, Heroicon::BookOpen));
    $module = new Module('academics', new Property(Module::Icon, Alias::NavigationDashboard), $resource);

    expect($resource->icon())->toBe('heroicon:book-open')
        ->and($module->icon())->toBe('navigation.dashboard')
        ->and($resource->validate()->isValid())->toBeTrue()
        ->and($module->validate()->isValid())->toBeTrue();
});
