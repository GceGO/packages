<?php

declare(strict_types=1);

use Gcego\Core\Extensions\ExtensionManager;
use Gcego\Core\Gcego;
use Gcego\Core\Lifecycle\FrameworkState;
use Gcego\Core\Packages\PackageManager;
use Gcego\Core\Tests\Fixtures\BootLog;
use Gcego\Core\Tests\Fixtures\FixtureExtension;
use Gcego\Core\Tests\Fixtures\FixturePackage;
use Gcego\Core\Tests\Fixtures\FormsServiceProvider;
use Gcego\Core\Tests\Fixtures\TablesServiceProvider;
use Illuminate\Support\Facades\Artisan;

it('previews loaded providers and extensions without mutating live boot state', function (): void {
    $this->rebuildApplication(['auto_boot' => false], [FormsServiceProvider::class, TablesServiceProvider::class]);
    $gcego = app(Gcego::class);
    $plan = $gcego->plan();

    expect($plan->isValid())->toBeTrue()
        ->and($plan->packages)->toBe(['gcego/core', 'gcego/tables', 'gcego/forms'])
        ->and($plan->extensions)->toBe(['forms.table-filters', 'tables.export'])
        ->and($plan->capabilities)->toBe(['forms', 'framework', 'tables', 'tables.export'])
        ->and($gcego->packages())->toBe([])
        ->and($gcego->state())->toBe(FrameworkState::Registered)
        ->and($gcego->timings())->toBe([])
        ->and(app(BootLog::class)->entries)->toBe([]);

    $result = $gcego->boot();
    expect($result->packages)->toBe($plan->packages)
        ->and($result->extensions)->toBe($plan->extensions);
});

it('reports dependency problems without initializing anything', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    $log = new BootLog;
    app(PackageManager::class)->register(FixturePackage::make('acme/a', requires: ['acme/missing'], log: $log));
    app(PackageManager::class)->register(FixturePackage::make('acme/b', requires: ['acme/absent'], log: $log));

    $plan = app(Gcego::class)->plan();

    expect($plan->isValid())->toBeFalse()
        ->and($plan->problems)->toHaveCount(2)
        ->and($plan->packages)->toBe([])
        ->and($log->entries)->toBe([]);
});

it('previews disabled packages and conditional extension skips', function (): void {
    $this->rebuildApplication(['auto_boot' => false, 'packages.disabled' => ['gcego/tables']], [FormsServiceProvider::class, TablesServiceProvider::class]);
    $plan = app(Gcego::class)->plan();

    expect($plan->disabledPackages)->toBe(['gcego/tables'])
        ->and($plan->packages)->toBe(['gcego/core', 'gcego/forms'])
        ->and($plan->skippedExtensions)->toBe(['forms.table-filters' => 'package gcego/tables is not active']);
});

it('reports extension planning failures and invalid configuration safely', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    app(ExtensionManager::class)->register(FixtureExtension::make('tables.export', requires: ['missing']));
    expect(app(Gcego::class)->plan()->problems[0])->toContain('requires the extension(s) missing');

    $this->rebuildApplication(['auto_boot' => false, 'name' => ['password=secret']]);
    expect(app(Gcego::class)->plan()->isValid())->toBeFalse()
        ->and(json_encode(app(Gcego::class)->plan()->toArray()))->not->toContain('password=secret');
});

it('exposes the plan through Artisan as JSON with failure exit codes', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    expect(Artisan::call('gcego:plan', ['--json' => true]))->toBe(0);
    $plan = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    expect($plan['packages'])->toBe(['gcego/core']);

    app(PackageManager::class)->register(FixturePackage::make('acme/a', requires: ['acme/missing']));
    $this->artisan('gcego:plan')->expectsOutputToContain('acme/missing')->assertFailed();
});

it('includes loaded providers in diagnostics before manual boot', function (): void {
    $this->rebuildApplication(['auto_boot' => false], [TablesServiceProvider::class]);
    $report = app(Gcego::class)->diagnose();

    expect($report->result('packages.dependencies')?->details['packages'])->toBe(['gcego/core', 'gcego/tables'])
        ->and($report->result('extensions.dependencies')?->details['extensions'])->toBe(['tables.export'])
        ->and(app(Gcego::class)->packages())->toBe([]);
});

it('uses cached metadata for explicitly registered packages during repeated previews', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    $package = FixturePackage::make('acme/test');
    app(PackageManager::class)->register($package);
    app(Gcego::class)->plan();
    app(Gcego::class)->plan();

    expect($package->descriptorCalls)->toBe(1);
});

it('uses the same configuration snapshot for preview and startup', function (): void {
    $this->rebuildApplication(['auto_boot' => false], [TablesServiceProvider::class]);
    $gcego = app(Gcego::class);
    $plan = $gcego->plan();
    config()->set('gcego.packages.disabled', ['gcego/tables']);
    expect($gcego->boot()->packages)->toBe($plan->packages);
});
