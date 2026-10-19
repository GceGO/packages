<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Registry\Registry;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->world = world();
    app(Registry::class)->register(new Blueprint('reports', new Permission('reports.view')));
    app(Registry::class)->register(new Blueprint('open'));
    Gate::define('reports.view', fn (User $user): bool => $user->name === 'Admin');

    Route::middleware(['web', 'gcego.authorize:blueprint:reports'])->get('/reports', fn () => 'reports');
    Route::middleware(['web', 'gcego.authorize:blueprint:open'])->get('/open', fn () => 'open');
    Route::middleware(['web', 'gcego.authorize:blueprint:ghost'])->get('/ghost', fn () => 'ghost');
});

it('enforces definition requirements on routes', function (): void {
    $this->get('/reports')->assertForbidden();
    $this->actingAs($this->world['teacher'])->get('/reports')->assertForbidden();
    $this->actingAs($this->world['admin'])->get('/reports')->assertOk()->assertSee('reports');
});

it('denies routes of unprotected and unknown definitions', function (): void {
    $this->actingAs($this->world['admin'])->get('/open')->assertForbidden();
    $this->actingAs($this->world['admin'])->get('/ghost')->assertNotFound();
});

it('does not apply its middleware to routes that do not ask for it', function (): void {
    Route::get('/plain', fn () => 'plain');

    $this->get('/plain')->assertOk();
    expect(app('router')->getMiddleware())->toHaveKeys(['gcego.authorize', 'gcego.tenant']);
});

it('does not reveal definition internals in denials', function (): void {
    $this->actingAs($this->world['teacher'])->getJson('/reports')->assertForbidden()->assertJsonMissingPath('exception')->assertDontSee('reports.view');
});
