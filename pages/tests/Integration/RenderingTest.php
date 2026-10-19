<?php

declare(strict_types=1);

use Gcego\Chroma\Slot;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Property;
use Gcego\Layouts\Components\Section;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Page;
use Gcego\Pages\PagesConfig;
use Gcego\Pages\Tests\Fixtures\Models\User;
use Gcego\Registry\Registry;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Requirements\Authenticated;

beforeEach(function (): void {
    $this->world = world();
});

function pageHtml(User $user, string $uri, int $institution = 1): string
{
    return squish((string) asMember($user, $institution)->get($uri)->assertOk()->getContent());
}

it('renders the page header: breadcrumbs, one h1, description and header links', function (): void {
    $html = pageHtml($this->world['admin'], '/admin/subjects/1');

    expect($html)->toContain('<title>Chemistry · ')
        ->and(substr_count($html, '<h1 '))->toBe(1)
        ->and($html)->toMatch('/<h1 id="gcego-page-subjects-view-[^"]+-title" class="[^"]*text-2xl[^"]*">Chemistry<\/h1>/')
        ->and($html)->toMatch('/<p class="[^"]*text-gcego-text-muted[^"]*">Subject Chemistry<\/p>/')
        ->and($html)->toMatch('/<nav aria-label="Breadcrumb" class="min-w-0"> <ol [^>]*> <li [^>]*> <a href="\/admin\/subjects" [^>]*>Subjects<\/a> <\/li> <li [^>]*> <svg [^>]*>.*?<\/svg> <span aria-current="page" [^>]*>Chemistry<\/span>/')
        ->and($html)->toMatch('/<div role="group" aria-label="Page actions"[^>]*> <a href="\/admin\/subjects\/1\/edit" class="[^"]*min-h-10[^"]*">.*?Edit<\/span><\/a>/')
        ->and($html)->toContain('data-gcego-page="page:subjects-view" aria-labelledby="gcego-page-subjects-view');
});

it('shows header links only to users who may open their target', function (): void {
    expect(pageHtml($this->world['admin'], '/admin/subjects/1'))->toContain('href="/admin/subjects/1/edit"')
        ->and(pageHtml($this->world['reader'], '/admin/subjects/1'))->not->toContain('href="/admin/subjects/1/edit"')
        ->and(pageHtml($this->world['editor'], '/admin/subjects'))->toContain('href="/admin/subjects/create"')
        ->and(pageHtml($this->world['reader'], '/admin/subjects'))->not->toContain('href="/admin/subjects/create"');
});

it('composes an index page around its table, scoped to the tenant', function (): void {
    $html = pageHtml($this->world['admin'], '/admin/subjects');

    expect($html)->toContain('wire:name="gcego-table"')
        ->and($html)->toContain('data-gcego-table')
        ->and($html)->toContain('Chemistry')
        ->and($html)->toContain('Physics')
        ->and($html)->not->toContain('Secret Biology')
        ->and($html)->toContain('Every subject of your institution.');
});

it('composes create and edit pages around their forms, without the form action in the header', function (): void {
    $create = pageHtml($this->world['editor'], '/admin/subjects/create');
    $edit = pageHtml($this->world['admin'], '/admin/subjects/1/edit');

    expect($create)->toContain('wire:name="gcego-form"')
        ->and($create)->toContain('wire:model')
        ->and($create)->not->toContain('aria-label="Page actions"')
        ->and($edit)->toContain('Edit Chemistry</h1>')
        ->and($edit)->toContain('&quot;data&quot;:[{&quot;name&quot;:&quot;Chemistry&quot;,&quot;code&quot;:&quot;CHE&quot;}')
        ->and($edit)->toMatch('/aria-label="Page actions".*data-gcego-action="delete"/')
        ->and($edit)->toMatch('/<a href="\/admin\/subjects"[^>]*>Subjects<\/a>.*<a href="\/admin\/subjects\/1"[^>]*>Chemistry<\/a>.*<span aria-current="page"[^>]*>Edit Chemistry<\/span>/');
});

it('composes a dashboard of independently authorized widgets', function (): void {
    $admin = pageHtml($this->world['admin'], '/admin');
    $editor = pageHtml($this->world['editor'], '/admin');

    expect($admin)->toContain('Dashboard</h1>')
        ->and($admin)->toContain('Subjects')
        ->and($admin)->toContain('Reports')
        ->and($editor)->toContain('Subjects')
        ->and($editor)->not->toContain('>Reports<');
});

it('renders layouts and content with a correct heading hierarchy', function (): void {
    $html = pageHtml($this->world['reader'], '/admin/about');

    expect($html)->toMatch('/<h1 [^>]*>About GCEGO<\/h1>/')
        ->and($html)->toMatch('/<h2 [^>]*>About this platform<\/h2>/')
        ->and($html)->toContain('<p>GCEGO helps candidates prepare for the GCE.</p> <p>It is &lt;b&gt;free&lt;/b&gt; &amp; open.</p>');
});

it('opens the tab named in the query string, ignoring unknown tabs', function (): void {
    $fees = pageHtml($this->world['admin'], '/admin/reports/overview?tab=fees');
    $unknown = pageHtml($this->world['admin'], '/admin/reports/overview?tab=%3Cscript%3E');

    expect($fees)->toMatch('/<div role="tabpanel"[^>]*hidden[^>]*>.*Attendance is above 90%/')
        ->and($fees)->not->toMatch('/<div role="tabpanel"[^>]*hidden[^>]*> <div [^>]*> <p>Fees are due/')
        ->and($unknown)->not->toContain('<script>')
        ->and($unknown)->toMatch('/<div role="tabpanel"[^>]*hidden[^>]*>.*Fees are due/');
});

it('shows an empty state when the user may see none of the content', function (): void {
    app(Registry::class)->register(new Page('restricted', 'Restricted', new Authenticated, new Property(Page::Empty, 'Nothing for you here.'), new Section('Secret', new Permission('reports.finance'), new Content('Hidden.'))));
    actAs($this->world['admin']);
    $html = squish(pages()->render('restricted')->toHtml());

    expect($html)->toMatch('/<div role="status" class="[^"]*border-dashed[^"]*">.*<p [^>]*>Nothing for you here.<\/p>/')
        ->and($html)->not->toContain('Hidden.');
});

it('applies page styling slots through gcego/chroma', function (): void {
    app(Registry::class)->register(new Page(
        'styled',
        'Styled',
        new Authenticated,
        new Property(Page::Width, 'narrow'),
        new Tailwind(new Slot(Tailwind::Title, ['text-4xl', 'text-gcego-primary']), new Slot(Tailwind::Body, ['gap-10'])),
        new Content('Text.'),
    ));
    actAs($this->world['admin']);
    $html = squish(pages()->render('styled')->toHtml());

    expect($html)->toMatch('/<h1 [^>]*class="[^"]*text-4xl[^"]*">Styled<\/h1>/')
        ->and($html)->not->toMatch('/<h1 [^>]*class="[^"]*text-2xl/')
        ->and($html)->toContain('text-gcego-primary')
        ->and($html)->toMatch('/class="[^"]*max-w-3xl[^"]*"/')
        ->and($html)->toMatch('/class="[^"]*gap-10[^"]*"/');
});

it('shows a loading indicator for page updates and keeps breadcrumbs optional', function (): void {
    $html = pageHtml($this->world['admin'], '/admin/archive/'.$this->world['notes'][0]->getKey());

    expect($html)->toContain('<div wire:loading.delay.long role="status"')
        ->and($html)->toContain('Loading…')
        ->and($html)->not->toContain('aria-label="Breadcrumb"');
});

it('starts breadcrumbs at the configured home page', function (): void {
    app()->instance(PagesConfig::class, PagesConfig::fromArray(['breadcrumbs' => ['home' => 'dashboard']]));
    actAs($this->world['admin']);

    expect(pages()->open('subjects.edit', 1)->header?->breadcrumbs->toArray())->toBe([
        ['label' => 'Dashboard', 'url' => '/admin', 'current' => false],
        ['label' => 'Subjects', 'url' => '/admin/subjects', 'current' => false],
        ['label' => 'Chemistry', 'url' => '/admin/subjects/1', 'current' => false],
        ['label' => 'Edit Chemistry', 'url' => '/admin/subjects/1/edit', 'current' => true],
    ]);
});

it('renders unlinked crumbs for ancestors the user may not open', function (): void {
    app(Registry::class)->register(new Page('reports.overview.fees', 'Fees', new Authenticated, new Content('Fees.')));
    actAs($this->world['editor']);

    expect(pages()->open('reports.overview.fees')->header?->breadcrumbs->toArray())->toBe([
        ['label' => 'Reports', 'url' => null, 'current' => false],
        ['label' => 'Fees', 'url' => null, 'current' => true],
    ]);
});

it('renders record pages keyed by UUID, ULID and slug', function (): void {
    expect(pageHtml($this->world['admin'], '/admin/catalogue/'.$this->world['documents'][0]->getKey()))->toContain('Subject SYL</h1>')
        ->and(pageHtml($this->world['admin'], '/admin/archive/'.$this->world['notes'][0]->getKey()))->toContain('Reminder')
        ->and(pageHtml($this->world['admin'], '/admin/topics/organic-chemistry'))->toContain('>ORG</h1>');

    asMember($this->world['admin'])->get('/admin/catalogue/'.$this->world['documents'][1]->getKey())->assertNotFound();
});
