<?php

declare(strict_types=1);

use Gcego\Core\Configuration\ConfigurationValidator;
use Gcego\Core\Diagnostics\DiagnosticStatus;
use Gcego\Core\Diagnostics\EnvironmentChecker;
use Gcego\Core\Diagnostics\FrameworkDiagnostics;
use Gcego\Core\Extensions\ExtensionManager;
use Gcego\Core\Gcego;
use Gcego\Core\Lifecycle\LifecycleManager;
use Gcego\Core\Packages\PackageManager;
use Gcego\Core\Tests\Fixtures\FixturePackage;
use Illuminate\Contracts\Config\Repository;

it('reports a healthy booted framework', function (): void {
    $report = app(Gcego::class)->diagnose();

    expect($report->isHealthy())->toBeTrue()
        ->and(array_slice(array_map(fn ($result): string => $result->check, $report->results()), 0, 8))->toBe([
            'php.version', 'php.extensions', 'laravel.version', 'configuration',
            'framework.registration', 'framework.boot', 'packages.dependencies', 'extensions.dependencies',
        ])
        ->and($report->result('framework.boot')?->details['state'])->toBe('booted')
        ->and($report->result('packages.components'))->toBeNull();
});

it('warns when the framework has not booted yet', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);

    expect(app(Gcego::class)->diagnose()->result('framework.boot')?->status)->toBe(DiagnosticStatus::Warning);
});

it('warns about unknown configuration keys', function (): void {
    $this->rebuildApplication(['debug' => true]);

    expect(app(Gcego::class)->diagnose()->result('configuration')?->status)->toBe(DiagnosticStatus::Warning);
});

it('reports an unregistered framework', function (): void {
    $diagnostics = new FrameworkDiagnostics(
        app(EnvironmentChecker::class),
        new ConfigurationValidator,
        app('config'),
        new LifecycleManager,
        new PackageManager,
        new ExtensionManager,
    );

    expect($diagnostics->run()->result('framework.registration')?->status)->toBe(DiagnosticStatus::Failed);
});

it('never throws and never leaks failure messages', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    app(PackageManager::class)->register(FixturePackage::make('acme/secretive', onBoot: function (): void {
        throw new RuntimeException('password=hunter2');
    }));

    try {
        app(Gcego::class)->boot();
    } catch (Throwable) {
        // Expected.
    }

    $report = app(Gcego::class)->diagnose();

    expect(json_encode($report->toArray(), JSON_THROW_ON_ERROR))->not->toContain('hunter2')
        ->and($report->result('framework.boot')?->message)->toContain('RuntimeException');
});

it('reports a non-array configuration', function (): void {
    config()->set('gcego', 'broken');

    expect(app(FrameworkDiagnostics::class)->run()->result('configuration')?->status)->toBe(DiagnosticStatus::Failed);
});

it('turns a crashing check into a failed result', function (): void {
    $this->rebuildApplication(['auto_boot' => false]);
    $broken = new FrameworkDiagnostics(
        app(EnvironmentChecker::class),
        new ConfigurationValidator,
        new class implements Repository
        {
            public function has($key): bool
            {
                return false;
            }

            public function get($key, $default = null): mixed
            {
                throw new RuntimeException('config store offline');
            }

            public function all(): array
            {
                return [];
            }

            public function set($key, $value = null): void {}

            public function prepend($key, $value): void {}

            public function push($key, $value): void {}
        },
        app(LifecycleManager::class),
        app(PackageManager::class),
        app(ExtensionManager::class),
    );

    $report = $broken->run();

    expect($report->result('configuration')?->status)->toBe(DiagnosticStatus::Failed)
        ->and($report->result('configuration')?->message)->toBe('The check could not run (RuntimeException).');
});
