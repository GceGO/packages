<?php

declare(strict_types=1);

use Gcego\Luminaries\Facades\Luminaries;
use Gcego\Luminaries\LuminariesConfig;
use Gcego\Luminaries\LuminariesServiceProvider;
use Orchestra\Testbench\Foundation\Application;

it('disables the per-icon Blade Icons components by default', function (): void {
    expect(config('blade-icons.components.disabled'))->toBeTrue();
});

it('registers the gcego:icons commands', function (string $command): void {
    expect(array_keys(Artisan::all()))->toContain($command);
})->with(['gcego:icons:list', 'gcego:icons:search', 'gcego:icons:check', 'gcego:icons:cache', 'gcego:icons:clear', 'gcego:icons:providers']);

it('builds the same configuration from a cached (var_export) config file', function (): void {
    $cached = eval('return '.var_export(config('luminaries'), true).';');

    expect(LuminariesConfig::fromArray($cached))->toEqual(LuminariesConfig::fromArray((array) config('luminaries')));
});

it('reports the providers it exposes', function (): void {
    expect(Luminaries::hasProvider('lucide'))->toBeTrue()
        ->and(array_keys(Luminaries::providers()))->toBe(['heroicon', 'heroicon-solid', 'heroicon-mini', 'heroicon-micro', 'lucide', 'custom', 'unsafe', 'inline']);
});

it('works when only the Luminaries provider is listed', function (): void {
    $app = Application::create(null, null, ['extra' => ['providers' => [LuminariesServiceProvider::class], 'dont-discover' => ['*']]]);

    expect($app->make(Gcego\Luminaries\Luminaries::class)->resolve('actions.edit')->canonical())->toBe('heroicon:pencil-square');
});
