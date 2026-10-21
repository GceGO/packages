<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Gcego\Core\Bootstrap\BootStatus;
use Gcego\Core\Diagnostics\DiagnosticStatus;
use Gcego\Core\Diagnostics\EnvironmentChecker;
use Gcego\Core\Exceptions\BootstrapException;
use Gcego\Core\Exceptions\ConfigurationException;
use Gcego\Core\Exceptions\InvalidLifecycleException;
use Gcego\Core\Exceptions\PackageDependencyException;
use Gcego\Core\Exceptions\PackageRegistrationException;
use Gcego\Core\Extensions\ExtensionManager;
use Gcego\Core\Gcego;
use Gcego\Core\Lifecycle\FrameworkState;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Core\Packages\PackageManager;
use Gcego\Core\Tests\Fixtures\BootLog;
use Gcego\Core\Tests\Fixtures\BrokenServiceProvider;
use Gcego\Core\Tests\Fixtures\FixtureExtension;
use Gcego\Core\Tests\Fixtures\FixturePackage;
use Gcego\Core\Tests\Fixtures\FormsServiceProvider;
use Gcego\Core\Tests\Fixtures\TablesServiceProvider;

it('discovers package providers and boots them in dependency order', function (): void {
    $this->rebuildApplication(providers: [FormsServiceProvider::class, TablesServiceProvider::class]);
    $gcego = app(Gcego::class);

    expect($gcego->bootResult()?->packages)->toBe(['gcego/core', 'gcego/tables', 'gcego/forms'])
        ->and(app(BootLog::class)->entries)->toBe([
            'package:gcego/tables',
            'package:gcego/forms',
            'extension:forms.table-filters',
            'extension:tables.export',
        ])
        ->and(array_map(fn (PackageDescriptor $descriptor): string => $descriptor->id, $gcego->packages()))->toBe(['gcego/core', 'gcego/forms', 'gcego/tables'])
        ->and($gcego->package('gcego/tables')->version)->toBe('1.0.0')
        ->and($gcego->hasCapability('tables'))->toBeTrue()
        ->and($gcego->hasCapability('tables.export'))->toBeTrue()
        ->and($gcego->hasCapability('payments'))->toBeFalse()
        ->and($gcego->hasExtension('tables.export'))->toBeTrue()
        ->and($gcego->isExtensionActive('forms.table-filters'))->toBeTrue()
        ->and($gcego->extensions())->toHaveCount(2);
});

it('switches optional integrations off when the other package is absent', function (): void {
    $this->rebuildApplication(providers: [FormsServiceProvider::class]);
    $gcego = app(Gcego::class);

    expect($gcego->bootResult()?->packages)->toBe(['gcego/core', 'gcego/forms'])
        ->and($gcego->isExtensionActive('forms.table-filters'))->toBeFalse()
        ->and($gcego->bootResult()?->skippedExtensions)->toBe(['forms.table-filters' => 'package gcego/tables is not active']);
});

it('does not boot disabled packages', function (): void {
    $this->rebuildApplication(['packages' => ['disabled' => ['gcego/tables']]], [FormsServiceProvider::class, TablesServiceProvider::class]);
    $gcego = app(Gcego::class);

    expect($gcego->bootResult()?->packages)->toBe(['gcego/core', 'gcego/forms'])
        ->and($gcego->hasPackage('gcego/tables'))->toBeTrue()
        ->and($gcego->isPackageActive('gcego/tables'))->toBeFalse()
        ->and($gcego->isExtensionActive('forms.table-filters'))->toBeFalse();
});

it('does not boot when disabled', function (): void {
    $this->rebuildApplication(['enabled' => false], [TablesServiceProvider::class]);
    $gcego = app(Gcego::class);

    expect($gcego->isEnabled())->toBeFalse()
        ->and($gcego->state())->toBe(FrameworkState::Registered)
        ->and($gcego->boot()->status)->toBe(BootStatus::Disabled)
        ->and($gcego->bootResult()?->status)->toBe(BootStatus::Disabled)
        ->and(app(BootLog::class)->entries)->toBe([]);
});

it('waits for a manual boot when auto_boot is false', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    $gcego = app(Gcego::class);

    expect($gcego->state())->toBe(FrameworkState::Registered);

    $result = $gcego->boot();

    expect($result->isBooted())->toBeTrue()
        ->and($result->durationMs)->toBeGreaterThanOrEqual(0.0)
        ->and($result->toArray()['status'])->toBe('booted')
        ->and($gcego->state())->toBe(FrameworkState::Booted);
});

it('boots idempotently', function (): void {
    $this->rebuildApplication(providers: [TablesServiceProvider::class]);
    $gcego = app(Gcego::class);
    $first = $gcego->bootResult();

    expect($gcego->boot())->toBe($first)
        ->and($gcego->boot())->toBe($first)
        ->and(app(BootLog::class)->entries)->toBe(['package:gcego/tables', 'extension:tables.export']);
});

it('lets explicitly registered packages and extensions join the boot', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    $log = new BootLog;
    app(PackageManager::class)->register(FixturePackage::make('acme/reports', requires: ['gcego/core'], capabilities: ['reports'], log: $log));
    app(ExtensionManager::class)->register(FixtureExtension::make('reports.pdf', whenPackages: ['acme/reports'], log: $log));

    app(Gcego::class)->boot();

    expect($log->entries)->toBe(['package:acme/reports', 'extension:reports.pdf'])
        ->and(app(Gcego::class)->hasCapability('reports'))->toBeTrue();
});

it('rejects registrations after boot', function (): void {
    app(PackageManager::class)->register(FixturePackage::make('acme/late'));
})->throws(PackageRegistrationException::class, 'registered after package registration was locked');

it('fails predictably on missing dependencies and reports it in diagnostics', function (): void {
    $this->rebuildApplication(['auto_boot' => false], [BrokenServiceProvider::class]);
    $gcego = app(Gcego::class);

    try {
        $gcego->boot();
        $this->fail('Expected a boot failure.');
    } catch (BootstrapException $exception) {
        expect($exception->getMessage())->toContain('GCEGO package "gcego/broken" requires "gcego/missing"')
            ->and($exception->getPrevious())->toBeInstanceOf(PackageDependencyException::class);
    }

    $report = $gcego->diagnose();

    expect($gcego->state())->toBe(FrameworkState::Failed)
        ->and($report->result('framework.boot')?->status)->toBe(DiagnosticStatus::Failed)
        ->and($report->result('framework.boot')?->message)->toContain(PackageDependencyException::class)
        ->and($report->result('packages.dependencies')?->status)->toBe(DiagnosticStatus::Failed);
});

it('recovers from a failed boot on retry', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    $attempts = 0;
    $log = new BootLog;
    app(PackageManager::class)->register(FixturePackage::make('acme/flaky', log: $log, onBoot: function () use (&$attempts): void {
        if (++$attempts === 1) {
            throw new RuntimeException('cache warming failed');
        }
    }));
    $gcego = app(Gcego::class);

    expect(fn () => $gcego->boot())->toThrow(BootstrapException::class, 'GCEGO package "acme/flaky" failed to boot: cache warming failed')
        ->and($gcego->state())->toBe(FrameworkState::Failed);

    expect($gcego->boot()->isBooted())->toBeTrue()
        ->and($gcego->state())->toBe(FrameworkState::Booted)
        ->and($log->entries)->toBe(['package:acme/flaky', 'package:acme/flaky']);
});

it('rejects re-entrant boots from package hooks', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    app(PackageManager::class)->register(FixturePackage::make('acme/reentrant', onBoot: fn () => app(Gcego::class)->boot()));

    try {
        app(Gcego::class)->boot();
        $this->fail('Expected a boot failure.');
    } catch (BootstrapException $exception) {
        expect($exception->getPrevious())->toBeInstanceOf(InvalidLifecycleException::class);
    }
});

it('refuses to boot with invalid configuration', function (): void {
    $this->rebuildApplication(['auto_boot' => false, 'name' => '']);
    $gcego = app(Gcego::class);

    try {
        $gcego->boot();
        $this->fail('Expected a boot failure.');
    } catch (BootstrapException $exception) {
        expect($exception->getPrevious())->toBeInstanceOf(ConfigurationException::class);
    }

    expect($gcego->state())->toBe(FrameworkState::Failed)
        ->and($gcego->diagnose()->result('configuration')?->status)->toBe(DiagnosticStatus::Failed);
});

it('fails the automatic boot loudly on invalid configuration', function (): void {
    try {
        $this->rebuildApplication(['name' => ['not', 'a', 'string']]);
        $this->fail('Expected the application boot to fail.');
    } catch (BootstrapException $exception) {
        expect($exception->getPrevious())->toBeInstanceOf(ConfigurationException::class)
            ->and($exception->getMessage())->toContain('gcego.name must be a non-empty string');
    }
});

it('blocks boot when environment checks fail and can skip them', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    app()->bind(EnvironmentChecker::class, fn ($app) => new EnvironmentChecker($app, phpVersion: '8.1.0'));

    expect(fn () => app(Gcego::class)->boot())->toThrow(BootstrapException::class, 'PHP 8.1.0 is older than the required 8.3.0.');

    $this->rebuildApplication(['auto_boot' => false, 'environment_checks' => false]);
    app()->bind(EnvironmentChecker::class, fn ($app) => new EnvironmentChecker($app, phpVersion: '8.1.0'));

    expect(app(Gcego::class)->boot()->isBooted())->toBeTrue();
});

it('exposes the framework version from Composer', function (): void {
    expect(app(Gcego::class)->version()->package)->toBe('gcego/core')
        ->and(app(Gcego::class)->version()->version)->toBe(InstalledVersions::getPrettyVersion('gcego/core'));
});

it('retains the disabled boot result', function (): void {
    $this->rebuildApplication(['enabled' => false]);
    $gcego = app(Gcego::class);

    expect($gcego->bootResult()?->status)->toBe(BootStatus::Disabled)
        ->and($gcego->isBooted())->toBeFalse();
});

it('exposes unique capabilities from active packages and extensions', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    app(PackageManager::class)->register(FixturePackage::make('acme/tables', capabilities: ['tables', 'shared']));
    app(ExtensionManager::class)->register(FixtureExtension::make('tables.export', capabilities: ['export', 'shared']));
    $gcego = app(Gcego::class);

    expect($gcego->capabilities())->toBe([]);
    $gcego->boot();

    expect($gcego->capabilities())->toBe(['export', 'framework', 'shared', 'tables'])
        ->and($gcego->extension('tables.export')->capabilities)->toBe(['export', 'shared'])
        ->and($gcego->hasCapability('export'))->toBeTrue();
});

it('identifies active capability providers and exposes dependency queries', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    app(PackageManager::class)->register(FixturePackage::make('acme/tables', requires: ['gcego/core'], capabilities: ['shared']));
    app(ExtensionManager::class)->register(FixtureExtension::make('tables.shared', capabilities: ['shared']));
    $gcego = app(Gcego::class);
    expect($gcego->capabilityProviders('shared'))->toBe(['packages' => [], 'extensions' => []]);
    $gcego->boot();
    expect($gcego->capabilityProviders('shared'))->toBe(['packages' => ['acme/tables'], 'extensions' => ['tables.shared']])
        ->and($gcego->packageDependencies('acme/tables'))->toBe(['gcego/core'])
        ->and($gcego->packageDependants('gcego/core'))->toBe(['acme/tables'])
        ->and($gcego->capabilityProviders('missing'))->toBe(['packages' => [], 'extensions' => []]);
});

it('rejects a non-array configuration during manual startup instead of silently using defaults', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    config()->set('gcego', 'password=secret');
    expect(fn () => app(Gcego::class)->boot())->toThrow(BootstrapException::class, 'gcego must be an array.');
    expect(app(Gcego::class)->plan()->problems[0])->not->toContain('password=secret');
});

it('locks the package set before dependency resolution even when resolution fails', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    $manager = app(PackageManager::class);
    $package = FixturePackage::make('acme/reports', requires: ['acme/missing']);
    $manager->register($package);
    $gcego = app(Gcego::class);

    expect(fn () => $gcego->boot())->toThrow(BootstrapException::class)
        ->and($manager->isLocked())->toBeTrue()
        ->and($manager->booted())->toBe([])
        ->and(fn () => $manager->register(FixturePackage::make('acme/missing')))
        ->toThrow(PackageRegistrationException::class, 'package registration was locked');

    // Discovery and existing-instance registration remain idempotent on retry.
    $manager->register($package);
    expect(fn () => $gcego->boot())->toThrow(BootstrapException::class)
        ->and($manager->isLocked())->toBeTrue()
        ->and($manager->has('acme/missing'))->toBeFalse();
});

it('runs package hooks against a closed registration set and preserves it on retry', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    $manager = app(PackageManager::class);
    $attempts = 0;
    $lockStates = [];
    $manager->register(FixturePackage::make('acme/reports', requires: ['gcego/core'], onBoot: function () use ($manager, &$attempts, &$lockStates): void {
        $lockStates[] = $manager->isLocked();
        if (++$attempts === 1) {
            $manager->register(FixturePackage::make('acme/late'));
        }
    }));
    $gcego = app(Gcego::class);

    expect(fn () => $gcego->boot())->toThrow(BootstrapException::class, 'package registration was locked')
        ->and($manager->has('acme/late'))->toBeFalse()
        ->and($manager->booted())->toBe(['gcego/core']);

    expect($gcego->boot()->packages)->toBe(['gcego/core', 'acme/reports'])
        ->and($lockStates)->toBe([true, true])
        ->and($manager->isLocked())->toBeTrue();
});
