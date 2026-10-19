<?php

declare(strict_types=1);

use Gcego\Swatches\Http\ExplorerController;
use Illuminate\Support\Facades\Gate;

it('is not routed unless enabled', function (): void {
    $this->get('/gcego/colors')->assertNotFound();
});

it('is refused outside the local environment by default', function (): void {
    $this->rebuildApplication(['gcego-swatches.explorer.enabled' => true]);
    $this->app['env'] = 'production';

    $this->get('/gcego/colors')->assertForbidden();
});

it('follows an application-defined gate', function (): void {
    $this->rebuildApplication(['gcego-swatches.explorer.enabled' => true]);
    $this->app['env'] = 'local';
    Gate::define(ExplorerController::Ability, static fn (): bool => false);

    $this->get('/gcego/colors')->assertForbidden();
});

it('renders palettes, tokens, previews, the audit and exports in local development', function (): void {
    $this->rebuildApplication(['gcego-swatches.explorer.enabled' => true]);
    $this->app['env'] = 'local';

    $this->get('/gcego/colors?theme=ocean&fg=muted-foreground&bg=surface')
        ->assertOk()
        ->assertSee('data-gcego-theme="ocean"', false)
        ->assertSee('Component preview')
        ->assertSee('sidebar-active-foreground')
        ->assertSee('WCAG 2.2 AA audit of GCEGO Ocean')
        ->assertSee('emerald')
        ->assertSee("'ocean' =>", false)
        ->assertDontSee('No hint path');
});

it('ignores unknown themes and malformed contrast input', function (): void {
    $this->rebuildApplication(['gcego-swatches.explorer.enabled' => true]);
    $this->app['env'] = 'local';

    $this->get('/gcego/colors?theme=%22%3E%3Cscript%3E&fg=%3Cscript%3E&bg=surface')
        ->assertOk()
        ->assertSee('data-gcego-theme="gcego"', false)
        ->assertSee('Not a color or token.')
        ->assertDontSee('<script>alert', false);
});

it('shows APCA and simulated color vision', function (): void {
    $this->rebuildApplication(['gcego-swatches.explorer.enabled' => true]);
    $this->app['env'] = 'local';

    $this->get('/gcego/colors')
        ->assertOk()
        ->assertSee('APCA (advisory)')
        ->assertSee('Lc ')
        ->assertSee('Deuteranopia')
        ->assertSee('Achromatopsia')
        ->assertSee('Typical vision');
});
