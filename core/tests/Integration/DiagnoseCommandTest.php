<?php

declare(strict_types=1);

use Gcego\Core\Extensions\ExtensionManager;
use Gcego\Core\Gcego;
use Gcego\Core\Tests\Fixtures\FixtureExtension;
use Illuminate\Support\Facades\Artisan;

it('prints readable diagnostics', function (): void {
    $this->artisan('gcego:diagnose')->expectsOutputToContain('framework.boot')->assertSuccessful();
});

it('provides valid JSON for monitoring', function (): void {
    expect(Artisan::call('gcego:diagnose', ['--json' => true]))->toBe(0);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($report['healthy'])->toBeTrue()
        ->and(array_column($report['checks'], 'check'))->toContain('extensions.dependencies');
});

it('fails on warnings only in strict mode without booting the framework', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);

    $this->artisan('gcego:diagnose')->assertSuccessful();
    $this->artisan('gcego:diagnose', ['--strict' => true])->assertFailed();

    expect(app(Gcego::class)->isBooted())->toBeFalse();
});

it('returns failure and explains invalid extension dependencies', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    app(ExtensionManager::class)->register(FixtureExtension::make('tables.export', requires: ['missing']));

    $this->artisan('gcego:diagnose', ['--json' => true])
        ->expectsOutputToContain('requires the extension(s) missing')
        ->assertFailed();
});
