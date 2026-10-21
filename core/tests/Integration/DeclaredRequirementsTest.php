<?php

declare(strict_types=1);

use Gcego\Core\Exceptions\BootstrapException;
use Gcego\Core\Exceptions\PackageRegistrationException;
use Gcego\Core\Extensions\ExtensionDescriptor;
use Gcego\Core\Extensions\ExtensionManager;
use Gcego\Core\Gcego;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Core\Packages\PackageManager;
use Gcego\Core\Tests\Fixtures\FixtureExtension;
use Gcego\Core\Tests\Fixtures\PlainPackage;

it('enforces declared dependency version constraints before any hooks run', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    $manager = app(PackageManager::class);
    $manager->register(new PlainPackage(new PackageDescriptor('acme/base', '1.0.0')));
    $manager->register(new PlainPackage(new PackageDescriptor('acme/consumer', requires: ['acme/base'], versionConstraints: ['acme/base' => '^2.0'])));
    $gcego = app(Gcego::class);
    expect($gcego->plan()->isValid())->toBeFalse()
        ->and(fn () => $gcego->boot())->toThrow(BootstrapException::class, 'unsupported version')
        ->and($manager->booted())->toBe([])
        ->and($gcego->diagnose()->result('packages.dependencies.unsupported_version')?->isFailure())->toBeTrue();
});

it('accepts compatible versions and reports constraints that lack version metadata', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    $manager = app(PackageManager::class);
    $manager->register(new PlainPackage(new PackageDescriptor('acme/base', '2.3.0')));
    $manager->register(new PlainPackage(new PackageDescriptor('acme/unknown')));
    $manager->register(new PlainPackage(new PackageDescriptor('acme/consumer', requires: ['acme/base', 'acme/unknown'], versionConstraints: ['acme/base' => '^2.0', 'acme/unknown' => '^1.0'])));
    $gcego = app(Gcego::class);
    expect($gcego->boot()->isBooted())->toBeTrue()
        ->and($gcego->diagnose()->result('packages.versions.acme/consumer')?->severity())->toBe('warning');
});

it('rejects invalid or unrelated constraint declarations without exposing their values', function (): void {
    expect(fn () => new PackageDescriptor('acme/consumer', requires: ['acme/base'], versionConstraints: ['acme/base' => 'password=secret']))->toThrow(PackageRegistrationException::class, 'constraint is invalid')
        ->and(fn () => new PackageDescriptor('acme/consumer', versionConstraints: ['acme/base' => '^1.0']))->toThrow(PackageRegistrationException::class, 'declared required or optional');
});

it('enforces required capabilities during extension planning and startup', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    app(ExtensionManager::class)->register(new FixtureExtension(new ExtensionDescriptor('acme.consumer', requiresCapabilities: ['missing.capability'])));
    $gcego = app(Gcego::class);
    expect($gcego->plan()->isValid())->toBeFalse()
        ->and(fn () => $gcego->boot())->toThrow(BootstrapException::class, 'requires capabilities')
        ->and($gcego->isExtensionActive('acme.consumer'))->toBeFalse()
        ->and($gcego->diagnose()->result('extensions.dependencies.capabilities')?->isFailure())->toBeTrue();
});

it('accepts capabilities from active package and prerequisite extension providers', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    app(PackageManager::class)->register(new PlainPackage(new PackageDescriptor('acme/base', capabilities: ['base.feature'])));
    $extensions = app(ExtensionManager::class);
    $extensions->register(new FixtureExtension(new ExtensionDescriptor('acme.provider', capabilities: ['extension.feature'])));
    $extensions->register(new FixtureExtension(new ExtensionDescriptor('acme.consumer', requires: ['acme.provider'], requiresCapabilities: ['base.feature', 'extension.feature'])));
    $gcego = app(Gcego::class);
    expect($gcego->plan()->isValid())->toBeTrue()
        ->and($gcego->boot()->extensions)->toBe(['acme.provider', 'acme.consumer'])
        ->and($gcego->diagnose()->isHealthy())->toBeTrue();
});

it('reports duplicate capability providers as informational unless exclusivity is declared', function (bool $exclusive): void {
    $this->rebuildApplication(['auto_boot' => false]);
    $manager = app(PackageManager::class);
    $manager->register(new PlainPackage(new PackageDescriptor('acme/a', capabilities: ['shared'], exclusiveCapabilities: $exclusive ? ['shared'] : [])));
    $manager->register(new PlainPackage(new PackageDescriptor('acme/b', capabilities: ['shared'])));
    $gcego = app(Gcego::class);
    $gcego->boot();
    $row = $gcego->diagnose()->result('capabilities.providers.shared');
    expect($row?->severity())->toBe($exclusive ? 'error' : 'info')
        ->and($row?->details['count'])->toBe(2);
})->with([false, true]);

it('provides separate codes for missing, disabled and circular package dependencies', function (string $problem): void {
    $this->rebuildApplication(['auto_boot' => false, 'packages.disabled' => $problem === 'disabled' ? ['acme/base'] : []]);
    $manager = app(PackageManager::class);
    $manager->register(new PlainPackage(new PackageDescriptor('acme/consumer', requires: ['acme/base'])));
    if ($problem !== 'missing') {
        $manager->register(new PlainPackage(new PackageDescriptor('acme/base', requires: $problem === 'circular' ? ['acme/consumer'] : [])));
    }
    expect(app(Gcego::class)->diagnose()->result('packages.dependencies.'.$problem)?->isFailure())->toBeTrue();
})->with(['missing', 'disabled', 'circular']);

it('provides separate codes for missing, disabled and circular extension dependencies', function (string $problem): void {
    $this->rebuildApplication(['auto_boot' => false, 'extensions.disabled' => $problem === 'disabled' ? ['acme.base'] : []]);
    $extensions = app(ExtensionManager::class);
    $extensions->register(new FixtureExtension(new ExtensionDescriptor('acme.consumer', requires: ['acme.base'])));
    if ($problem !== 'missing') {
        $extensions->register(new FixtureExtension(new ExtensionDescriptor('acme.base', requires: $problem === 'circular' ? ['acme.consumer'] : [])));
    }
    expect(app(Gcego::class)->diagnose()->result('extensions.dependencies.'.$problem)?->isFailure())->toBeTrue();
})->with(['missing', 'disabled', 'circular']);
