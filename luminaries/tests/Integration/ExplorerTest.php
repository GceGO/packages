<?php

declare(strict_types=1);

use Gcego\Luminaries\Http\ExplorerController;
use Gcego\Luminaries\LuminariesServiceProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

function enableExplorer(): void
{
    config()->set('luminaries.explorer.enabled', true);
    app()->getProvider(LuminariesServiceProvider::class)?->boot();
}

it('does not register the explorer route unless enabled', function (): void {
    $this->get('/gcego/icons')->assertNotFound();
});

it('forbids the explorer outside the local environment by default', function (): void {
    enableExplorer();

    $this->get('/gcego/icons')->assertForbidden();
});

it('shows matching icons, aliases and copyable Blade usage to authorized developers', function (): void {
    enableExplorer();
    Gate::define(ExplorerController::Ability, static fn (?Authenticatable $user = null): bool => true);

    $this->get('/gcego/icons?q=graduation&provider=lucide&size=xl')
        ->assertOk()
        ->assertSee('lucide:graduation-cap')
        ->assertSee('<x-gcego-icon name="lucide:graduation-cap" />')
        ->assertSee('size-8', false)
        ->assertDontSee('heroicon:academic-cap');
});

it('escapes the search query', function (): void {
    enableExplorer();
    Gate::define(ExplorerController::Ability, static fn (?Authenticatable $user = null): bool => true);

    $this->get('/gcego/icons?q='.urlencode('"><script>alert(1)</script>'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false);
});
