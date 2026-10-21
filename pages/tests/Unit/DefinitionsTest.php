<?php

declare(strict_types=1);

use Gcego\Actions\Action;
use Gcego\Actions\ActionPlacement;
use Gcego\Actions\Create;
use Gcego\Components\Exceptions\InvalidComponentException;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Infolists\Entries\Text as TextEntry;
use Gcego\Infolists\Infolist;
use Gcego\Layouts\Components\Section;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Components\PageLink;
use Gcego\Pages\Exceptions\InvalidPageException;
use Gcego\Pages\Page;
use Gcego\Pages\PagesConfig;
use Gcego\Pages\Records\RecordKey;
use Gcego\Pages\Tests\Fixtures\Definitions;
use Gcego\Pages\Tests\Fixtures\Models\Subject;
use Gcego\Pages\Types\CreatePage;
use Gcego\Pages\Types\CustomPage;
use Gcego\Pages\Types\DashboardPage;
use Gcego\Pages\Types\EditPage;
use Gcego\Pages\Types\IndexPage;
use Gcego\Pages\Types\ViewPage;
use Gcego\Security\Requirements\Authenticated;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;

/**
 * @return list<string>
 */
function problemsOf(Page $page): array
{
    return array_map(strval(...), $page->validate()->errors());
}

it('builds a minimal page with property syntax or shorthand', function (): void {
    $properties = new Page(new Property(Page::Name, 'about'), new Property(Page::Title, 'About GCEGO'), new Authenticated, new Section('About', new Content('Hello.')));
    $shorthand = new Page('about', 'About GCEGO', new Authenticated, new Section('About', new Content('Hello.')));

    expect($properties->validate()->isValid())->toBeTrue()
        ->and($properties->pageName())->toBe('about')
        ->and($properties->pageTitle())->toBe('About GCEGO')
        ->and($properties::pageType())->toBe('custom')
        ->and($properties->normalize())->toBe($shorthand->normalize())
        ->and($properties->registryName())->toBe('about')
        ->and($properties->path())->toBe('about')
        ->and($properties->routeName())->toBe('about');
});

it('derives paths, route names and registry names from dotted names', function (Page $page, string $registry, string $path, bool $record): void {
    expect($page->registryName())->toBe($registry)
        ->and($page->path())->toBe($path)
        ->and($page->requiresRecord())->toBe($record)
        ->and($page->routeName())->toBe($page->pageName())
        ->and(problemsOf($page))->toBe([]);
})->with([
    'index' => [fn (): Page => Definitions::subjects(), 'subjects', 'subjects', false],
    'create' => [fn (): Page => Definitions::createSubject(), 'subjects-create', 'subjects/create', false],
    'edit' => [fn (): Page => Definitions::editSubject(), 'subjects-edit', 'subjects/{record}/edit', true],
    'view' => [fn (): Page => Definitions::viewSubject(), 'subjects-view', 'subjects/{record}', true],
    'custom' => [fn (): Page => Definitions::reports(), 'reports-overview', 'reports/overview', false],
    'explicit path' => [fn (): Page => Definitions::members(), 'institutions-members', 'institutions/{institution}/members', false],
    'dashboard at the prefix' => [fn (): Page => Definitions::dashboard(), 'dashboard', '', false],
    'record custom page' => [fn (): Page => new CustomPage(new Property(Page::Name, 'subjects.report'), new Property(Page::Record, true), new Authenticated, new Source(Subject::class), new Content('Report')), 'subjects-report', 'subjects/{record}/report', true],
    'single-segment record page' => [fn (): Page => new ViewPage(new Property(Page::Name, 'subject'), new Authenticated, new Source(Subject::class), new Infolist(new TextEntry('name'))), 'subject', 'subject/{record}', true],
]);

it('keeps definitions immutable and their identity stable', function (): void {
    $page = Definitions::viewSubject();
    $copy = $page->withProperty(new Property(Page::Title, 'Other'));

    expect($copy)->not->toBe($page)
        ->and($page->pageTitle())->toBe(':record')
        ->and($copy->pageTitle())->toBe('Other')
        ->and(Definitions::viewSubject()->normalize())->toBe($page->normalize())
        ->and(Definitions::viewSubject()->registryName())->toBe($page->registryName());
});

it('rejects invalid pages with readable reasons', function (Closure $make, string $reason): void {
    expect(implode("\n", problemsOf($make())))->toContain($reason);
})->with([
    'no name' => [fn (): Page => new Page(new Authenticated), 'Name'],
    'upper-case name' => [fn (): Page => new Page('Subjects', 'Subjects'), 'lowercase segments'],
    'index without table' => [fn (): Page => new IndexPage('subjects', 'Subjects', new Source(Subject::class)), 'a index page needs a Table'],
    'index without source' => [fn (): Page => new IndexPage('subjects', 'Subjects', new Table(new Text('name'))), 'needs a gcego/data Source'],
    'create without form' => [fn (): Page => new CreatePage('subjects.create', 'New', new Source(Subject::class)), 'a create page needs a Form'],
    'view without infolist' => [fn (): Page => new ViewPage('subjects.view', 'View', new Source(Subject::class)), 'a view page needs a Infolist'],
    'record page without source' => [fn (): Page => new CustomPage(new Property(Page::Name, 'x.report'), new Property(Page::Record, true), new Content('x')), 'needs a gcego/data Source'],
    'record path without {record}' => [fn (): Page => new ViewPage(new Property(Page::Name, 'subjects.view'), new Property(Page::Path, 'subjects/view'), new Source(Subject::class), new Infolist(new TextEntry('name'))), 'needs {record} in its Path'],
    '{record} on a non-record page' => [fn (): Page => new Page(new Property(Page::Name, 'x'), new Property(Page::Path, 'x/{record}')), '{record} is reserved'],
    'unknown Where parameter' => [fn (): Page => new Page(new Property(Page::Name, 'x'), new Property(Page::Where, ['id' => '[0-9]+'])), 'not a parameter of the path'],
    'anchored constraint' => [fn (): Page => new Page(new Property(Page::Name, 'x'), new Property(Page::Path, 'x/{id}'), new Property(Page::Where, ['id' => '^[0-9]+$'])), 'without anchors'],
    'broken constraint' => [fn (): Page => new Page(new Property(Page::Name, 'x'), new Property(Page::Path, 'x/{id}'), new Property(Page::Where, ['id' => '[0-9+'])), 'without anchors'],
    'unsafe segment' => [fn (): Page => new Page(new Property(Page::Name, 'x'), new Property(Page::Path, 'x/../admin')), 'path segment ".."'],
    'duplicate parameter' => [fn (): Page => new Page(new Property(Page::Name, 'x'), new Property(Page::Path, '{a}/{a}')), 'appears twice'],
    'optional parameter' => [fn (): Page => new Page(new Property(Page::Name, 'x'), new Property(Page::Path, 'x/{id?}')), 'optional parameters are not supported'],
    'own parent' => [fn (): Page => new Page(new Property(Page::Name, 'x'), new Property(Page::Parent, 'x')), 'its own Parent'],
    'bad redirect' => [fn (): Page => new Page(new Property(Page::Name, 'x'), new Property(Page::RedirectTo, 'https://evil.test')), 'view, edit, index, self, none or a page name'],
    'bad middleware' => [fn (): Page => new Page(new Property(Page::Name, 'x'), new Property(Page::Middleware, ['auth', 'x y'])), 'middleware'],
    'bad record key' => [fn (): Page => new Page(new Property(Page::Name, 'x'), new Property(Page::RecordKey, 'email')), 'record_key'],
    'empty dashboard' => [fn (): Page => new DashboardPage('dashboard', 'Dashboard'), 'child'],
]);

it('refuses to render or register an invalid page', function (): void {
    expect(fn () => (new IndexPage('subjects', 'Subjects'))->assertValid())->toThrow(InvalidComponentException::class);
});

it('adds the action a form submits, placed in the form footer only', function (): void {
    $create = new CreatePage('subjects.create', 'New', new Source(Subject::class), new Form(new TextInput('name')));
    $edit = new EditPage('subjects.edit', 'Edit', new Source(Subject::class), new Form(new TextInput('name')));
    $own = new CreatePage('subjects.create', 'New', new Source(Subject::class), new Form(new TextInput('name')), new Create('Add', new Property(Action::Placement, ActionPlacement::Header)));
    $actions = static fn (Page $page): array => array_map(static fn (Action $action): array => [$action->actionName(), $action->placements()], array_values(array_filter($page->children()->all(), static fn ($child): bool => $child instanceof Action)));

    expect($actions($create))->toBe([['create', ['form-footer']]])
        ->and($actions($edit))->toBe([['edit', ['form-footer']]])
        ->and($actions($own))->toBe([['create', ['header']]])
        ->and($edit->recordAbility())->toBe('update')
        ->and($create->recordAbility())->toBe('view');
});

it('exposes navigation metadata only for pages that opt in', function (): void {
    expect(Definitions::subjects()->navigation())->toBe(['enabled' => true, 'label' => 'Subjects', 'group' => 'Academic', 'order' => 0, 'icon' => 'academic-cap'])
        ->and(Definitions::createSubject()->navigation()['enabled'])->toBeFalse()
        ->and(Definitions::dashboard()->navigation())->toMatchArray(['enabled' => true, 'order' => -10, 'icon' => 'home']);
});

it('accepts tables, forms, infolists, layouts, widgets and page components, nothing else', function (): void {
    $page = new Page('mixed', 'Mixed', new Authenticated, new Section('A', new Content('x')), new PageLink('Back', 'subjects'));

    expect($page->validate()->isValid())->toBeTrue()
        ->and(implode(' ', problemsOf(new Page('mixed', 'Mixed', new Text('name')))))->toContain('Text');
});

it('validates Content and PageLink', function (): void {
    expect((new Content("One\n\nTwo\nlines"))->paragraphs())->toBe(['One', "Two\nlines"])
        ->and((new Content(''))->validate()->isValid())->toBeFalse()
        ->and((new PageLink('Edit', 'subjects.edit'))->validate()->isValid())->toBeTrue()
        ->and((new PageLink('Edit', 'App\\Pages\\Edit'))->validate()->isValid())->toBeFalse()
        ->and((new PageLink('Edit', 'subjects.edit', new Property(PageLink::Parameters, ['id' => ['x']])))->validate()->isValid())->toBeFalse();
});

it('knows the shapes of record keys', function (string $kind, string $key, bool $valid): void {
    expect(RecordKey::valid($kind, $key))->toBe($valid);
})->with([
    ['id', '42', true],
    ['id', '4a', false],
    ['uuid', '9b2f7c1e-3a4d-4e5f-8a9b-0c1d2e3f4a5b', true],
    ['uuid', '9b2f7c1e', false],
    ['ulid', '01HZX3K5N8Q9R2S4T6V8W0Y2Z4', true],
    ['ulid', '01HZX3K5N8Q9R2S4T6V8W0Y2ZU', false],
    ['slug', 'organic-chemistry', true],
    ['slug', 'Organic Chemistry', false],
    ['key', 'abc_DEF-1', true],
    ['key', '../etc/passwd', false],
    ['key', str_repeat('a', 192), false],
    ['key', '', false],
]);

it('validates the configuration', function (array $config, string $reason): void {
    expect(fn () => PagesConfig::fromArray($config))->toThrow(InvalidPageException::class, $reason);
})->with([
    [['routing' => ['prefix' => 'ad min']], 'routing.prefix'],
    [['routing' => ['name_prefix' => 'gcego']], 'name_prefix'],
    [['routing' => ['middleware' => ['web', 'a b']]], 'middleware'],
    [['authorization' => ['default' => 'allow']], 'authorization.default'],
    [['pages' => ['default_width' => 'huge']], 'default_width'],
    [['pages' => ['record_key' => 'email']], 'record_key'],
    [['lifecycle' => ['hooks' => [stdClass::class]]], 'lifecycle.hooks'],
    [['rendering' => ['vite' => ['../secret.js']]], 'rendering.vite'],
    [['breadcrumbs' => ['enabled' => 'yes']], 'must be true or false'],
]);

it('constructs pages without touching the database', function (): void {
    expect(queriesDuring(static fn (): array => Definitions::all()))->toBe([]);
});
