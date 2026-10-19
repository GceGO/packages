<?php

declare(strict_types=1);

use Gcego\Forms\Livewire\FormHost;
use Gcego\Panels\Exceptions\PanelAccessDeniedException;
use Gcego\Studio\Tests\Fixtures\Models\Subject;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
});

it('sends guests to the login page', function (): void {
    $this->get('/admin')->assertRedirect('/login');
    $this->get('/admin/subjects')->assertRedirect('/login');
    $this->get('/school/1')->assertRedirect('/login');
});

it('forbids a signed-in user without the panel ability', function (): void {
    asMember($this->world['reader'])->get('/admin')->assertForbidden();
    asMember($this->world['reader'])->get('/admin/subjects')->assertForbidden();
    asMember($this->world['reader'])->get('/admin/subjects/create')->assertForbidden();
});

it('lets a permitted user navigate the panel: dashboard, module index, create, view and edit pages', function (): void {
    $home = asMember($this->world['editor'])->get('/admin');
    $home->assertRedirect();
    $dashboard = html(asMember($this->world['editor'])->get('/admin/overview')->assertOk());

    expect($dashboard)->toContain('Overview')->toContain('Everything at a glance.')->toContain('href="/admin/subjects"')
        ->and($dashboard)->not->toContain('href="/admin/reports"');   // view-reports is Admin-only

    $index = html(asMember($this->world['editor'])->get('/admin/subjects')->assertOk());
    expect($index)->toContain('Chemistry')->toContain('Physics')->not->toContain('Biology')->toContain('href="/admin/subjects/create"');

    $chemistry = $this->world['chemistry']->getKey();
    expect(html(asMember($this->world['editor'])->get('/admin/subjects/create')->assertOk()))->toContain('wire:name="gcego-form"')
        ->and(html(asMember($this->world['editor'])->get('/admin/subjects/'.$chemistry)->assertOk()))->toContain('Chemistry')->toContain('wire:name="gcego-infolist"')
        ->and(html(asMember($this->world['editor'])->get('/admin/subjects/'.$chemistry.'/edit')->assertOk()))->toContain('wire:name="gcego-form"');

    asMember($this->world['editor'])->get('/admin/reports')->assertForbidden();
    asMember($this->world['admin'])->get('/admin/reports')->assertOk();
});

it('creates and edits records through gcego/forms and gcego/actions, scoped to the current tenant', function (): void {
    actAs($this->world['editor']);
    Livewire::test(FormHost::class, ['definition' => 'page:academics-subjects-create'])
        ->set('data.name', 'Geography')->set('data.code', 'GEO')
        ->call('submit')->assertDispatched('gcego-action-completed');

    $created = Subject::query()->where('code', 'GEO')->firstOrFail();
    expect($created->institution_id)->toBe(1);

    Livewire::test(FormHost::class, ['definition' => 'page:academics-subjects-edit', 'record' => $created->getKey()])
        ->assertSet('data.name', 'Geography')->set('data.name', 'World Geography')
        ->call('submit')->assertDispatched('gcego-action-completed');

    expect($created->fresh()?->name)->toBe('World Geography');

    Livewire::test(FormHost::class, ['definition' => 'page:academics-subjects-create'])
        ->set('data.name', '')->call('submit')->assertHasErrors(['data.name', 'data.code']);
    expect(Subject::query()->count())->toBe(4);
});

it('denies create and edit to a member whose policy forbids them', function (): void {
    asMember($this->world['admin'])->get('/admin')->assertRedirect();
    Gate::define('access-admin', static fn (): bool => true);

    asMember($this->world['reader'])->get('/admin/subjects')->assertOk();
    asMember($this->world['reader'])->get('/admin/subjects/create')->assertForbidden();
    asMember($this->world['reader'])->get('/admin/subjects/'.$this->world['chemistry']->getKey().'/edit')->assertForbidden();
});

it('never reveals another tenant\'s records', function (): void {
    $biology = $this->world['biology']->getKey();

    asMember($this->world['editor'])->get('/admin/subjects/'.$biology)->assertNotFound();
    asMember($this->world['editor'])->get('/admin/subjects/'.$biology.'/edit')->assertNotFound();

    asMember($this->world['reader'])->get('/school/1/subjects')->assertOk();
    asMember($this->world['reader'])->get('/school/2/subjects')->assertNotFound();   // gcego/panels: a tenant you do not belong to does not exist for you
    expect(html(asMember($this->world['south'], 2)->get('/school/2/subjects')->assertOk()))->toContain('Biology')->not->toContain('Chemistry');

    actAs($this->world['editor']);
    Livewire::test(FormHost::class, ['definition' => 'page:academics-subjects-edit', 'record' => $biology])->assertForbidden();   // out-of-tenant record: refused, fail-closed
    expect($this->world['biology']->fresh()?->name)->toBe('Biology');
});

it('re-checks panel access on every Livewire update', function (): void {
    $snapshot = snapshotOf(html(asMember($this->world['editor'])->get('/admin/subjects')), 'gcego-table');
    expect($snapshot)->not->toBe('');

    $this->actingAs($this->world['editor']->fresh());
    livewireUpdate($snapshot, [['method' => '$refresh', 'params' => []]])->assertOk();

    $this->actingAs($this->world['reader']->fresh());
    $replayed = livewireUpdate($snapshot, [['method' => '$refresh', 'params' => []]])->assertForbidden();

    expect($replayed->exception)->toBeInstanceOf(PanelAccessDeniedException::class);
});

it('serves several panels side by side, each with its own access rule and layout', function (): void {
    asMember($this->world['reader'])->get('/school/1')->assertSuccessful();
    asMember($this->world['reader'])->get('/admin')->assertForbidden();

    expect(studio()->panels())->toBe(['admin', 'school'])
        ->and(collect(studio()->inspect()['panels'])->pluck('identity')->all())->toBe(['panel:admin', 'panel:school']);
});
