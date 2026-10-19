<?php

declare(strict_types=1);

use Gcego\Forms\Livewire\FormHost;
use Gcego\Modules\Exceptions\ModuleNotFoundException;
use Gcego\Modules\Operation;
use Gcego\Modules\Tests\Fixtures\Models\Subject;
use Gcego\Pages\Livewire\PageHost;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
});

it('never resolves modules, resources or pages named by the browser beyond the registry', function (): void {
    expect(fn () => modules()->module('Gcego\\Modules\\Module'))->toThrow(ModuleNotFoundException::class)
        ->and(fn () => modules()->resource('academics', '../subjects'))->toThrow(ModuleNotFoundException::class)
        ->and(modules()->locate('page:'.Subject::class))->toBeNull();

    actAs($this->world['admin']);
    Livewire::test(PageHost::class, ['page' => 'page:academics-unknown'])->assertNotFound();
});

it('re-authorizes Livewire updates after a permission is revoked', function (): void {
    $html = (string) asMember($this->world['editor'])->get('/admin/subjects/1/edit')->assertOk()->getContent();
    $snapshot = snapshotOf($html, 'gcego-form');
    $this->world['editor']->forceFill(['name' => 'Reader'])->save();

    $response = livewireUpdate($snapshot, [['method' => 'submit', 'params' => []]], ['data.name' => 'Changed']);

    // Denied: either the request is refused or the action reports the denial; nothing is saved.
    expect(in_array($response->status(), [200, 403], true))->toBeTrue()
        ->and((string) $response->getContent())->not->toContain('"status":"success"')
        ->and(Subject::query()->findOrFail(1)->name)->toBe('Chemistry');
});

it('never serializes models or hidden attributes into pages or Livewire state', function (): void {
    $html = (string) asMember($this->world['admin'])->get('/admin/subjects/1')->assertOk()->getContent();
    $edit = (string) asMember($this->world['admin'])->get('/admin/subjects/1/edit')->assertOk()->getContent();

    expect($html.$edit)->not->toContain('SECRET-CHE')
        ->and(snapshotOf($html, 'gcego-page'))->not->toContain('Chemistry')->not->toContain(Subject::class);
});

it('keeps tenants apart on every generated page', function (): void {
    foreach (['/admin/subjects/4', '/admin/subjects/4/edit', '/admin/subjects/4/topics', '/admin/subjects/4/topics/3', '/admin/subjects/1/topics/3'] as $uri) {
        asMember($this->world['admin'])->get($uri)->assertNotFound();
    }

    expect(squish((string) asMember($this->world['admin'])->get('/admin/subjects')->getContent()))->not->toContain('Biology');
    expect(squish((string) asMember($this->world['south'], 2)->get('/admin/subjects')->getContent()))->toContain('Biology')->not->toContain('Chemistry');
});

it('refuses cross-tenant mutations through actions', function (): void {
    actAs($this->world['admin']);
    expect(runAction('page:academics-subjects-edit', 'delete', 4)->status->value)->toBe('not_found');

    actAs($this->world['editor']);
    // Livewire::test answers every denial with 403 (out of scope is 404 over HTTP, see "keeps tenants apart").
    Livewire::test(FormHost::class, ['definition' => 'page:academics-subjects-edit', 'record' => 4])->assertForbidden();
    expect(Subject::query()->withoutGlobalScopes()->findOrFail(4)->name)->toBe('Biology');
});

it('gives no route without pages: operations are never implied', function (): void {
    expect(modules()->page('academics', 'tags', Operation::Edit))->toBeNull()
        ->and(collect(app('router')->getRoutes()->getRoutes())->map->getName()->filter(fn ($name) => str_contains((string) $name, 'notes'))->all())->toBe([]);
});
