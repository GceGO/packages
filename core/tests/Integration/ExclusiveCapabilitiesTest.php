<?php

declare(strict_types=1);

use Gcego\Core\Diagnostics\DiagnosticStatus;
use Gcego\Core\Exceptions\BootstrapException;
use Gcego\Core\Exceptions\ExtensionException;
use Gcego\Core\Exceptions\PackageDependencyException;
use Gcego\Core\Extensions\ExtensionDescriptor;
use Gcego\Core\Extensions\ExtensionManager;
use Gcego\Core\Gcego;
use Gcego\Core\Lifecycle\FrameworkState;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Core\Packages\PackageManager;
use Gcego\Core\Tests\Fixtures\BootLog;
use Gcego\Core\Tests\Fixtures\BrokenServiceProvider;
use Gcego\Core\Tests\Fixtures\FixtureExtension;
use Gcego\Core\Tests\Fixtures\FixturePackage;

it('refuses to boot packages that share an exclusive capability before any hook runs', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    $log = new BootLog;
    app(PackageManager::class)->register(new FixturePackage(new PackageDescriptor('acme/ledger', capabilities: ['ledger'], exclusiveCapabilities: ['ledger']), $log));
    app(PackageManager::class)->register(new FixturePackage(new PackageDescriptor('acme/books', capabilities: ['ledger']), $log));
    $gcego = app(Gcego::class);

    try {
        $gcego->boot();
        $this->fail('Expected a boot failure.');
    } catch (BootstrapException $exception) {
        expect($exception->getPrevious())->toBeInstanceOf(PackageDependencyException::class)
            ->and($exception->getMessage())->toContain('Capability "ledger" is exclusive but is provided by acme/books, acme/ledger');
    }

    expect($log->entries)->toBe([])
        ->and($gcego->state())->toBe(FrameworkState::Failed)
        ->and($gcego->plan()->problems)->toHaveCount(1)
        ->and($gcego->diagnose()->result('packages.capabilities.exclusive')?->status)->toBe(DiagnosticStatus::Failed);
});

it('boots when the conflicting provider is disabled and allows shared ordinary capabilities', function (): void {
    $this->rebuildApplication(['auto_boot' => false, 'packages.disabled' => ['acme/books']]);
    app(PackageManager::class)->register(FixturePackage::make('acme/reports', capabilities: ['ledger.export']));
    app(PackageManager::class)->register(FixturePackage::make('acme/exports', capabilities: ['ledger.export']));
    app(PackageManager::class)->register(new FixturePackage(new PackageDescriptor('acme/ledger', capabilities: ['ledger'], exclusiveCapabilities: ['ledger'])));
    app(PackageManager::class)->register(new FixturePackage(new PackageDescriptor('acme/books', capabilities: ['ledger'])));

    expect(app(Gcego::class)->boot()->isBooted())->toBeTrue()
        ->and(app(Gcego::class)->capabilityProviders('ledger')['packages'])->toBe(['acme/ledger'])
        ->and(app(Gcego::class)->capabilityProviders('ledger.export')['packages'])->toBe(['acme/exports', 'acme/reports']);
});

it('refuses to activate an extension that conflicts with an exclusive capability', function (Closure $register): void {
    $this->rebuildApplication(['auto_boot' => false]);
    $log = new BootLog;
    $register($log);
    $gcego = app(Gcego::class);

    try {
        $gcego->boot();
        $this->fail('Expected a boot failure.');
    } catch (BootstrapException $exception) {
        expect($exception->getPrevious())->toBeInstanceOf(ExtensionException::class)
            ->and($exception->getMessage())->toContain('Capability "search" is exclusive');
    }

    expect($log->entries)->not->toContain('extension:acme.search')
        ->and($gcego->extensions())->toHaveCount(1)
        ->and($gcego->isExtensionActive('acme.search'))->toBeFalse()
        ->and($gcego->plan()->problems[0] ?? '')->toContain('Capability "search" is exclusive')
        ->and($gcego->diagnose()->result('extensions.capabilities.exclusive')?->status)->toBe(DiagnosticStatus::Failed);
})->with([
    'the extension declares exclusivity' => [function (BootLog $log): void {
        app(PackageManager::class)->register(FixturePackage::make('acme/catalog', capabilities: ['search'], log: $log));
        app(ExtensionManager::class)->register(new FixtureExtension(new ExtensionDescriptor('acme.search', capabilities: ['search'], exclusiveCapabilities: ['search']), $log));
    }],
    'a package declares exclusivity' => [function (BootLog $log): void {
        app(PackageManager::class)->register(new FixturePackage(new PackageDescriptor('acme/catalog', capabilities: ['search'], exclusiveCapabilities: ['search']), $log));
        app(ExtensionManager::class)->register(FixtureExtension::make('acme.search', capabilities: ['search'], log: $log));
    }],
]);

it('explains that package problems block extension planning instead of reporting a crashed check', function (): void {
    $this->rebuildApplication(['auto_boot' => false], [BrokenServiceProvider::class]);

    $result = app(Gcego::class)->diagnose()->result('extensions.dependencies');

    expect($result?->status)->toBe(DiagnosticStatus::Warning)
        ->and($result?->message)->toBe('Package dependency problems prevent extension planning; see packages.dependencies.');
});
