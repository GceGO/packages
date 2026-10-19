<?php

declare(strict_types=1);

use Gcego\Luminaries\Facades\Luminaries;

it('lists providers with their icon counts', function (): void {
    $this->artisan('gcego:icons:providers')
        ->expectsOutputToContain('heroicon (default)')
        ->expectsOutputToContain('Lucide')
        ->expectsOutputToContain('Fixture icons')
        ->assertSuccessful();
});

it('lists aliases with what they resolve to', function (): void {
    $this->artisan('gcego:icons:list', ['--group' => 'status'])
        ->expectsOutputToContain('status.danger')
        ->expectsOutputToContain('heroicon:x-circle')
        ->doesntExpectOutputToContain('actions.edit')
        ->assertSuccessful();
});

it('lists the icons of one provider as json', function (): void {
    $this->artisan('gcego:icons:list', ['provider' => 'custom', '--format' => 'json'])
        ->expectsOutputToContain('"custom:gcego-logo"')
        ->assertSuccessful();
});

it('fails to list an unknown provider', function (): void {
    $this->artisan('gcego:icons:list', ['provider' => 'fontawesome'])->assertFailed();
});

it('searches icons and prints the Blade usage', function (): void {
    $this->artisan('gcego:icons:search', ['query' => 'graduation', '--provider' => 'lucide'])
        ->expectsOutputToContain('<x-gcego-icon name="lucide:graduation-cap" />')
        ->assertSuccessful();
});

it('passes the check for a valid configuration', function (): void {
    configureLuminaries(['collections' => ['custom' => ['path' => fixtures('icons/trusted')]]]);

    $this->artisan('gcego:icons:check')->expectsOutputToContain('Icons OK')->assertSuccessful();
});

it('fails the check with actionable diagnostics', function (): void {
    configureLuminaries([
        'aliases' => ['actions.shelve' => 'lucide:archive-box', 'actions.delete' => 'heroicon:trash'],
        'collections' => ['custom' => ['path' => fixtures('icons/trusted')], 'unsafe' => ['path' => fixtures('icons/unsafe')], 'gone' => ['path' => fixtures('icons/missing')]],
        'providers' => ['heroicon' => ['set' => 'heroicons', 'variant' => 'o'], 'phosphor' => ['set' => 'phosphor-icons']],
    ]);

    $this->artisan('gcego:icons:check')
        ->expectsOutputToContain('actions.shelve: Mapping "lucide:archive-box" does not resolve')
        ->expectsOutputToContain('Duplicate: the configured alias repeats the preset mapping "heroicon:trash"')
        ->expectsOutputToContain('unsafe:script: Rejected by the trusted SVG policy')
        ->expectsOutputToContain('the directory does not exist')
        ->expectsOutputToContain('The Blade Icons set "phosphor-icons" is not registered')
        ->assertFailed();
});

it('caches and clears the icon manifest, which then answers lookups', function (): void {
    $path = (string) config('luminaries.cache.path');

    $this->artisan('gcego:icons:cache')->expectsOutputToContain('Icon manifest cached')->assertSuccessful();

    expect(is_file($path))->toBeTrue()
        ->and(Luminaries::manifest()->has('lucide', 'graduation-cap'))->toBeTrue()
        ->and(Luminaries::manifest()->has('lucide', 'nope'))->toBeFalse()
        ->and(Luminaries::exists('lucide:graduation-cap'))->toBeTrue();

    $this->artisan('gcego:icons:clear')->assertSuccessful();

    expect(is_file($path))->toBeFalse();
});

it('ignores a manifest written for another configuration', function (): void {
    $this->artisan('gcego:icons:cache')->assertSuccessful();
    configureLuminaries(['aliases' => ['actions.edit' => 'lucide:pencil']]);

    expect(Luminaries::manifest()->isFresh())->toBeFalse()
        ->and(Luminaries::manifest()->has('lucide', 'pencil'))->toBeNull();

    $this->artisan('gcego:icons:check')->expectsOutputToContain('The icon manifest is stale');
    Luminaries::manifest()->clear();
});
