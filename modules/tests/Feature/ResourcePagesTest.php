<?php

declare(strict_types=1);

use Gcego\Forms\Exceptions\InvalidStateException;
use Gcego\Forms\Livewire\FormHost;
use Gcego\Modules\Operation;
use Gcego\Modules\Tests\Fixtures\Models\Subject;
use Gcego\Pages\Livewire\PageHost;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
});

it('registers one named route per generated page through gcego/pages', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with((string) $route->getName(), 'gcego.academics.'))->mapWithKeys(fn ($route) => [$route->getName() => $route->uri()]);

    expect($routes->all())->toMatchArray([
        'gcego.academics.subjects.index' => 'admin/subjects',
        'gcego.academics.subjects.create' => 'admin/subjects/create',
        'gcego.academics.subjects.view' => 'admin/subjects/{record}',
        'gcego.academics.subjects.edit' => 'admin/subjects/{record}/edit',
        'gcego.academics.subjects.topics.index' => 'admin/subjects/{parent}/topics',
        'gcego.academics.subjects.topics.create' => 'admin/subjects/{parent}/topics/create',
        'gcego.academics.subjects.topics.view' => 'admin/subjects/{parent}/topics/{record}',
        'gcego.academics.subjects.topics.edit' => 'admin/subjects/{parent}/topics/{record}/edit',
        'gcego.academics.subjects.tags.index' => 'admin/subjects/{parent}/tags',
        'gcego.academics.tags.index' => 'admin/tags',
        'gcego.academics.overview' => 'admin/academics/overview',
    ])->and($routes->keys())->not->toContain('gcego.academics.tags.create', 'gcego.academics.tags.view');

    expect(modules()->url('academics', 'subjects', Operation::Edit, 1))->toBe('/admin/subjects/1/edit')
        ->and(modules()->url('academics', 'subjects', Operation::Index, parameters: ['parent' => 1], relation: 'topics'))->toBe('/admin/subjects/1/topics')
        ->and(modules()->url('academics', 'tags', Operation::Create))->toBeNull();
});

it('composes the index page from the declared table, scoped to the tenant', function (): void {
    $html = squish((string) asMember($this->world['reader'])->get('/admin/subjects')->assertOk()->getContent());

    expect($html)->toContain('<h1')->toContain('Subjects')
        ->and($html)->toContain('wire:name="gcego-table"')
        ->and($html)->not->toContain('href="/admin/subjects/create"');

    expect(squish((string) asMember($this->world['editor'])->get('/admin/subjects')->getContent()))->toContain('href="/admin/subjects/create"')->toContain('New Subject');
});

it('renders the create, view and edit pages around the declared form and infolist', function (): void {
    $create = squish((string) asMember($this->world['editor'])->get('/admin/subjects/create')->assertOk()->getContent());
    $view = squish((string) asMember($this->world['editor'])->get('/admin/subjects/1')->assertOk()->getContent());
    $edit = squish((string) asMember($this->world['editor'])->get('/admin/subjects/1/edit')->assertOk()->getContent());

    expect($create)->toContain('Create Subject')->toContain('wire:name="gcego-form"')
        ->and($view)->toContain('Chemistry')->toContain('wire:name="gcego-infolist"')->toContain('href="/admin/subjects/1/edit"')->toContain('href="/admin/subjects/1/topics"')->toContain('href="/admin/subjects/1/tags"')
        ->and($edit)->toContain('Edit Chemistry')->toContain('wire:name="gcego-form"');
});

it('creates, edits and deletes through gcego/forms and gcego/actions', function (): void {
    actAs($this->world['editor']);
    Livewire::test(FormHost::class, ['definition' => 'page:academics-subjects-create'])
        ->set('data.name', 'Geography')
        ->set('data.code', 'GEO')
        ->call('submit')
        ->assertDispatched('gcego-action-completed');

    $created = Subject::query()->where('code', 'GEO')->firstOrFail();
    expect($created->getAttribute('institution_id'))->toBe(1);

    Livewire::test(FormHost::class, ['definition' => 'page:academics-subjects-edit', 'record' => $created->getKey()])
        ->assertSet('data.name', 'Geography')
        ->set('data.name', 'World Geography')
        ->call('submit')
        ->assertDispatched('gcego-action-completed');

    expect($created->fresh()?->getAttribute('name'))->toBe('World Geography');

    Livewire::test(PageHost::class, ['page' => 'page:academics-subjects-create'])
        ->dispatch('gcego-action-completed', result: ['status' => 'success', 'action' => 'page:academics-subjects-create/action:create', 'record_key' => $created->getKey(), 'data' => []])
        ->assertRedirect('/admin/subjects/'.$created->getKey());
});

it('never mass-assigns undeclared fields', function (): void {
    actAs($this->world['editor']);

    expect(fn () => Livewire::test(FormHost::class, ['definition' => 'page:academics-subjects-create'])->set('data.institution_id', 2))->toThrow(InvalidStateException::class)
        ->and(fn () => Livewire::test(FormHost::class, ['definition' => 'page:academics-subjects-create'])->set('data.internal_notes', 'pwned'))->toThrow(InvalidStateException::class);
});

it('validates through gcego/forms and saves nothing on errors', function (): void {
    actAs($this->world['editor']);

    Livewire::test(FormHost::class, ['definition' => 'page:academics-subjects-create'])
        ->set('data.name', '')
        ->call('submit')
        ->assertHasErrors(['data.name', 'data.code'])
        ->assertNotDispatched('gcego-action-completed');

    expect(Subject::query()->count())->toBe(4);
});

it('generates only the pages a resource declares', function (): void {
    $tags = modules()->resource('academics', 'tags');

    expect($tags->operations)->toBe([Operation::Index])
        ->and(modules()->resource('notes', 'notes')->operations)->toBe([]);
    asMember($this->world['admin'])->get('/admin/tags')->assertOk();
    asMember($this->world['admin'])->get('/admin/tags/create')->assertNotFound();
});

it('renders module pages with the module\'s requirements', function (): void {
    asMember($this->world['reader'])->get('/admin/academics/overview')->assertOk()->assertSee('Academic Overview');
    auth()->logout();
    app()->forgetScopedInstances();
    $this->get('/admin/academics/overview')->assertRedirect('/login');
});

it('keeps the generated pages accessible: one h1, breadcrumbs, labelled forms', function (): void {
    foreach (['/admin/subjects', '/admin/subjects/create', '/admin/subjects/1', '/admin/subjects/1/edit', '/admin/subjects/1/topics'] as $uri) {
        $html = (string) asMember($this->world['admin'])->get($uri)->assertOk()->getContent();

        expect(substr_count($html, '<h1'))->toBe(1)
            ->and($html)->toContain('<main');
    }

    $view = (string) asMember($this->world['admin'])->get('/admin/subjects/1/topics')->getContent();
    expect($view)->toMatch('/<nav[^>]*aria-label="[^"]+"[^>]*>.*Subjects/s');

    $create = (string) asMember($this->world['admin'])->get('/admin/subjects/create')->getContent();
    preg_match_all('/<input[^>]*id="([^"]+)"[^>]*type="text"|<input[^>]*type="text"[^>]*id="([^"]+)"/', $create, $inputs);
    foreach (array_filter([...$inputs[1], ...$inputs[2]]) as $id) {
        expect($create)->toContain('for="'.$id.'"');
    }
});
