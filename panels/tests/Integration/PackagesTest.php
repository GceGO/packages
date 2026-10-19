<?php

declare(strict_types=1);

use Gcego\Core\Packages\PackageManager;
use Gcego\Panels\Http\Middleware\AuthorizePanel;
use Livewire\Livewire;

it('integrates with the GCEGO packages it builds on', function (): void {
    $descriptor = app(PackageManager::class)->descriptor('gcego/panels');
    $installed = array_map(static fn ($package): string => $package->id, app(PackageManager::class)->descriptors());

    expect($descriptor->requires)->toContain('gcego/modules', 'gcego/pages', 'gcego/navigation', 'gcego/security', 'gcego/chroma')
        ->and($descriptor->capabilities)->toContain('panels', 'panels.access', 'panels.switching')
        ->and($installed)->toContain('gcego/panels', 'gcego/modules', 'gcego/pages');
});

it('registers its middleware, also for Livewire updates', function (): void {
    expect(app('router')->getMiddleware()[AuthorizePanel::Alias] ?? null)->toBe(AuthorizePanel::class)
        ->and(Livewire::getPersistentMiddleware())->toContain(AuthorizePanel::class);
});

it('keeps its configuration cacheable', function (): void {
    $config = config('gcego-panels');

    expect(eval('return '.var_export($config, true).';'))->toBe($config);
});

it('takes over gcego/pages routing and layout while it routes pages', function (): void {
    expect(config('gcego-pages.routing.enabled'))->toBeFalse()
        ->and(config('gcego-pages.rendering.layout'))->toBe('gcego-panels::document');
});

it('keeps an application\'s own page layout', function (): void {
    $this->reboot(['gcego-pages.rendering.layout' => 'layouts.admin']);

    expect(config('gcego-pages.rendering.layout'))->toBe('layouts.admin');
});
