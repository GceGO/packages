<?php

declare(strict_types=1);

use Gcego\Core\Bootstrap\BootstrapContext;
use Gcego\Core\Configuration\GcegoConfig;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Diagnostics\CoreDiagnostics;
use Gcego\Core\Events\FrameworkBooting;
use Gcego\Core\Exceptions\BootstrapException;
use Gcego\Core\Exceptions\ExtensionException;
use Gcego\Core\Exceptions\InvalidLifecycleException;
use Gcego\Core\Exceptions\PackageRegistrationException;
use Gcego\Core\Extensions\ExtensionManager;
use Gcego\Core\Gcego;
use Gcego\Core\Lifecycle\LifecycleManager;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Core\Packages\PackageManager;
use Gcego\Core\Support\FrameworkVersion;
use Gcego\Core\Tests\Fixtures\DeferredPackageProvider;
use Gcego\Core\Tests\Fixtures\FixtureExtension;
use Gcego\Core\Tests\Fixtures\FixturePackage;
use Gcego\Core\Tests\Fixtures\InvalidDescriptorProvider;
use Gcego\Core\Tests\Fixtures\PlainPackage;

it('resolves the complete Core inspector and includes its checks', function (): void {
    expect(app(CoreDiagnostics::class))->toBeInstanceOf(CoreDiagnostics::class);
    $report = app(Gcego::class)->diagnose();
    expect($report->result('framework.identity'))->not->toBeNull();
});

it('reports identity, environment, integration and structured health metadata', function (): void {
    $report = app(Gcego::class)->diagnose();
    foreach (['framework.identity', 'framework.build', 'composer.runtime', 'runtime.platform', 'application.environment', 'lifecycle.history', 'packages.inventory', 'packages.order', 'packages.ordering', 'extensions.inventory', 'extensions.order', 'configuration.required', 'configuration.deprecated', 'configuration.dynamic_classes', 'configuration.packages', 'configuration.extensions', 'laravel.configuration_cache', 'laravel.provider', 'laravel.bindings', 'laravel.lifecycle', 'laravel.deferred_providers', 'laravel.commands', 'startup.consistency', 'capabilities.available', 'performance.bootstrap', 'security.production_debug', 'security.diagnostic_access', 'security.redaction'] as $code) {
        expect($report->result($code))->not->toBeNull();
    }
    expect($report->health())->toBe('healthy')
        ->and($report->result('framework.identity')?->details['name'])->toBe('GCEGO Studio')
        ->and($report->result('runtime.platform')?->details['sapi'])->toBe(PHP_SAPI)
        ->and($report->result('laravel.bindings')?->isFailure())->toBeFalse()
        ->and($report->toStructuredArray()['generatedAt'])->toBe($report->generatedAt)
        ->and(new DateTimeImmutable($report->generatedAt))->toBeInstanceOf(DateTimeImmutable::class);
});

it('reports package failures and affected dependants with profiling disabled and without secrets', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    $manager = app(PackageManager::class);
    $manager->register(FixturePackage::make('acme/a', onBoot: function (): never {
        throw new RuntimeException('password=do-not-expose');
    }));
    $manager->register(FixturePackage::make('acme/b', requires: ['acme/a']));
    app(ExtensionManager::class)->register(FixtureExtension::make('acme.integration', whenPackages: ['acme/b']));
    $gcego = app(Gcego::class);
    expect(fn () => $gcego->boot())->toThrow(BootstrapException::class);
    $report = $gcego->diagnose();
    expect($gcego->timings())->toBe([])
        ->and($report->health())->toBe('critical')
        ->and($report->result('packages.status.acme/a')?->details['status'])->toBe('failed')
        ->and($report->result('startup.failure')?->package)->toBe('acme/a')
        ->and($report->result('startup.failure')?->details['blocked_packages'])->toBe(['acme/b'])
        ->and($report->result('startup.failure')?->details['blocked_extensions'])->toBe(['acme.integration'])
        ->and($report->result('performance.bootstrap')?->details['duration_ms'])->toBeGreaterThanOrEqual(0)
        ->and($report->result('startup.failure')?->recommendedResolution())->not->toBeNull()
        ->and(json_encode($report, JSON_THROW_ON_ERROR))->not->toContain('do-not-expose');
});

it('collects opt-in detailed timings and flags slow package and extension hooks', function (): void {
    $this->rebuildApplication(['auto_boot' => false, 'diagnostics.profiling' => true, 'diagnostics.slow_package_ms' => 0.5, 'diagnostics.slow_extension_ms' => 0.5, 'diagnostics.slow_boot_ms' => 0.5]);
    app(PackageManager::class)->register(FixturePackage::make('acme/slow', onBoot: function (): void {
        usleep(2000);
    }));
    app(ExtensionManager::class)->register(FixtureExtension::make('acme.slow', onActivate: function (): void {
        usleep(2000);
    }));
    $gcego = app(Gcego::class);
    $gcego->boot();
    $report = $gcego->diagnose();
    $slow = array_filter($report->warnings(), fn ($row) => str_starts_with($row->check, 'performance.'));
    expect($gcego->timings())->toHaveCount(3)
        ->and($slow)->toHaveCount(3)
        ->and($report->result('performance.bootstrap')?->details['profiling'])->toBeTrue();
    foreach (['registration_ms', 'dependencies_ms', 'packages_ms', 'extensions_ms'] as $phase) {
        expect($report->result('performance.bootstrap')?->details[$phase])->toBeGreaterThanOrEqual(0);
    }
});

it('records duplicate and late package and extension registration attempts safely', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    $packages = app(PackageManager::class);
    $extensions = app(ExtensionManager::class);
    $packages->register(FixturePackage::make('acme/a'));
    $extensions->register(FixtureExtension::make('acme.a'));
    expect(fn () => $packages->register(FixturePackage::make('acme/a')))->toThrow(PackageRegistrationException::class);
    expect(fn () => $extensions->register(FixtureExtension::make('acme.a')))->toThrow(ExtensionException::class);
    app(Gcego::class)->boot();
    expect(fn () => $packages->register(FixturePackage::make('acme/late')))->toThrow(PackageRegistrationException::class);
    expect(fn () => $extensions->register(FixtureExtension::make('acme.late')))->toThrow(ExtensionException::class);
    $report = app(Gcego::class)->diagnose();
    foreach (['packages.registration.duplicate', 'extensions.registration.duplicate', 'packages.registration.late', 'extensions.registration.late'] as $code) {
        expect($report->result($code)?->severity())->toBe('warning');
    }
});

it('reports discovery failures without leaking descriptor exception messages', function (): void {
    $this->rebuildApplication(['auto_boot' => false], [InvalidDescriptorProvider::class]);
    expect(fn () => app(Gcego::class)->boot())->toThrow(BootstrapException::class);
    $report = app(Gcego::class)->diagnose();
    expect($report->result('packages.discovery')?->isFailure())->toBeTrue()
        ->and($report->result('packages.discovery.failed'))->not->toBeNull()
        ->and(json_encode($report, JSON_THROW_ON_ERROR))->not->toContain('provider-secret');
});

it('identifies loaded deferred GCEGO providers without loading deferred services', function (): void {
    $this->rebuildApplication(['auto_boot' => false], [DeferredPackageProvider::class]);
    $report = app(Gcego::class)->diagnose();
    expect($report->result('laravel.deferred_providers')?->isFailure())->toBeTrue()
        ->and($report->result('packages.discovery')?->isFailure())->toBeTrue();
});

it('reports broken Core service bindings while other checks continue', function (): void {
    $gcego = app(Gcego::class);
    app()->bind(FrameworkVersion::class, function (): never {
        throw new RuntimeException('password=binding-secret');
    });
    $report = $gcego->diagnose();
    expect($report->result('laravel.bindings')?->isFailure())->toBeTrue()
        ->and($report->result('security.redaction'))->not->toBeNull()
        ->and(json_encode($report, JSON_THROW_ON_ERROR))->not->toContain('binding-secret');
});

it('reports invalid transitions, production debug and unsafe environment combinations', function (): void {
    expect(fn () => app(LifecycleManager::class)->beginBoot())->toThrow(InvalidLifecycleException::class);
    app()->detectEnvironment(fn () => 'production');
    config()->set('app.debug', true);
    config()->set('gcego.environment_checks', false);
    $report = app(Gcego::class)->diagnose();
    expect($report->result('lifecycle.transitions')?->severity())->toBe('warning')
        ->and($report->result('security.production_debug')?->severity())->toBe('warning')
        ->and($report->result('configuration.environment')?->severity())->toBe('warning')
        ->and($report->result('lifecycle.history')?->details['transitions'])->not->toBeEmpty();
});

it('warns about unknown disabled references without autoloading configured classes', function (): void {
    $this->rebuildApplication(['packages.disabled' => ['acme/unknown'], 'extensions.disabled' => ['acme.unknown'], 'diagnostics.checks' => ['UnsafeConfiguredClass']]);
    $loads = [];
    $autoload = function (string $class) use (&$loads): void {
        $loads[] = $class;
    };
    spl_autoload_register($autoload);
    try {
        $report = app(Gcego::class)->diagnose();
    } finally {
        spl_autoload_unregister($autoload);
    }
    expect($report->result('configuration.packages')?->severity())->toBe('warning')
        ->and($report->result('configuration.extensions')?->severity())->toBe('warning')
        ->and($loads)->not->toContain('UnsafeConfiguredClass');
});

it('separates public uptime summaries from internal diagnostic reports', function (): void {
    $report = app(Gcego::class)->diagnose();
    expect(array_keys($report->publicSummary()))->toBe(['health', 'healthy', 'generatedAt'])
        ->and(json_encode($report->publicSummary(), JSON_THROW_ON_ERROR))->not->toContain('GCEGO', 'version', 'packages', 'php', 'exception');
});

it('flags descriptor versions that disagree with installed Composer metadata', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    app(PackageManager::class)->register(new PlainPackage(new PackageDescriptor('pestphp/pest', '0.0.1')));
    $report = app(Gcego::class)->diagnose();
    expect($report->result('packages.composer.pestphp/pest')?->isFailure())->toBeTrue();
});

it('detects dependency ordering violations and partial initialization after direct manager misuse', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    $manager = app(PackageManager::class);
    $manager->register(new PlainPackage(new PackageDescriptor('acme/consumer', requires: ['acme/base'])));
    $manager->register(new PlainPackage(new PackageDescriptor('acme/base')));
    $manager->boot(['acme/consumer'], new BootstrapContext(app(), GcegoConfig::defaults(), ['acme/consumer', 'acme/base'], []));
    $report = app(Gcego::class)->diagnose();
    expect($report->result('packages.ordering')?->isFailure())->toBeTrue()
        ->and($report->result('startup.consistency')?->severity())->toBe('warning');
});

it('retains recursive startup incidents after the failing operation is isolated', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    app('events')->listen(FrameworkBooting::class, fn () => app(Gcego::class)->boot());
    app(Gcego::class)->boot();
    expect(app(Gcego::class)->diagnose()->result('framework.recursive_boot')?->severity())->toBe('warning');
});

it('reports failed extension roots and blocked extension dependants', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    $extensions = app(ExtensionManager::class);
    $extensions->register(FixtureExtension::make('acme.a', onActivate: function (): never {
        throw new RuntimeException('token=hidden');
    }));
    $extensions->register(FixtureExtension::make('acme.b', requires: ['acme.a']));
    expect(fn () => app(Gcego::class)->boot())->toThrow(BootstrapException::class);
    $report = app(Gcego::class)->diagnose();
    expect($report->result('startup.failure')?->extension)->toBe('acme.a')
        ->and($report->result('startup.failure')?->details['blocked_extensions'])->toBe(['acme.b'])
        ->and($report->result('extensions.inventory')?->details['failed'])->toBe(['acme.a']);
});

it('rejects late registration before executing unregistered descriptor code', function (): void {
    $manager = app(PackageManager::class);
    $package = new class implements PackageInterface
    {
        public int $calls = 0;

        public function descriptor(): PackageDescriptor
        {
            $this->calls++;
            throw new RuntimeException('password=descriptor-secret');
        }
    };
    expect(fn () => $manager->register($package))->toThrow(PackageRegistrationException::class)
        ->and($package->calls)->toBe(0)
        ->and(json_encode(app(Gcego::class)->diagnose(), JSON_THROW_ON_ERROR))->not->toContain('descriptor-secret', '/home/');
});

it('warns when production debug configuration is truthy', function (mixed $debug): void {
    app()->detectEnvironment(fn () => 'production');
    config()->set('app.debug', $debug);
    expect(app(Gcego::class)->diagnose()->result('security.production_debug')?->severity())->toBe('warning');
})->with([true, 1, 'true']);
