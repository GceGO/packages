<?php

declare(strict_types=1);

use Gcego\Core\Bootstrap\FrameworkBootstrapper;
use Gcego\Core\Configuration\ConfigurationValidator;
use Gcego\Core\Configuration\GcegoConfig;
use Gcego\Core\Contracts\FrameworkInterface;
use Gcego\Core\Diagnostics\EnvironmentChecker;
use Gcego\Core\Diagnostics\FrameworkDiagnostics;
use Gcego\Core\Extensions\ExtensionManager;
use Gcego\Core\Gcego;
use Gcego\Core\GcegoServiceProvider;
use Gcego\Core\Lifecycle\FrameworkState;
use Gcego\Core\Lifecycle\LifecycleManager;
use Gcego\Core\Packages\PackageManager;
use Gcego\Core\Support\FrameworkVersion;
use Illuminate\Support\ServiceProvider;

it('resolves every public service', function (string $service): void {
    expect(app($service))->toBeInstanceOf($service);
})->with([
    Gcego::class,
    FrameworkInterface::class,
    GcegoConfig::class,
    LifecycleManager::class,
    PackageManager::class,
    ExtensionManager::class,
    FrameworkBootstrapper::class,
    FrameworkVersion::class,
    EnvironmentChecker::class,
    FrameworkDiagnostics::class,
    ConfigurationValidator::class,
]);

it('shares framework-wide singletons', function (string $service): void {
    expect(app($service))->toBe(app($service));
})->with([
    Gcego::class,
    GcegoConfig::class,
    LifecycleManager::class,
    PackageManager::class,
    ExtensionManager::class,
    FrameworkBootstrapper::class,
    FrameworkVersion::class,
]);

it('builds stateless services fresh', function (string $service): void {
    expect(app($service))->not->toBe(app($service));
})->with([EnvironmentChecker::class, FrameworkDiagnostics::class, ConfigurationValidator::class]);

it('aliases the framework contract to the entry point', function (): void {
    expect(app(FrameworkInterface::class))->toBe(app(Gcego::class));
});

it('merges the package configuration', function (): void {
    expect(config('gcego.name'))->toBe('GCEGO Admin Studio')
        ->and(config('gcego.enabled'))->toBeTrue()
        ->and(config('gcego.packages.disabled'))->toBe([]);
});

it('applies application overrides', function (): void {
    $this->rebuildApplication(['name' => 'School Console', 'extensions' => ['disabled' => ['tables.export']]]);

    expect(app(GcegoConfig::class)->name())->toBe('School Console')
        ->and(app(Gcego::class)->name())->toBe('School Console')
        ->and(app(GcegoConfig::class)->disabledExtensions())->toBe(['tables.export'])
        ->and(config('gcego.packages.disabled'))->toBe([]);
});

it('publishes the configuration file under its tag', function (): void {
    expect(ServiceProvider::pathsToPublish(GcegoServiceProvider::class, GcegoServiceProvider::ConfigTag))
        ->toBe([GcegoServiceProvider::configPath() => config_path('gcego.php')]);
});

it('declares Laravel package discovery metadata', function (): void {
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect(is_array($manifest) ? $manifest['extra']['laravel']['providers'] : null)->toBe([GcegoServiceProvider::class]);
});

it('boots automatically after Laravel boots', function (): void {
    $gcego = app(Gcego::class);

    expect($gcego->state())->toBe(FrameworkState::Booted)
        ->and($gcego->isBooted())->toBeTrue()
        ->and($gcego->bootResult()?->packages)->toBe(['gcego/core'])
        ->and($gcego->isPackageActive('gcego/core'))->toBeTrue()
        ->and($gcego->hasCapability('framework'))->toBeTrue();
});

it('ignores repeated provider registration', function (): void {
    app()->register(GcegoServiceProvider::class);
    app()->register(GcegoServiceProvider::class);

    expect(app()->getProviders(GcegoServiceProvider::class))->toHaveCount(1)
        ->and(app(Gcego::class)->packages())->toHaveCount(1)
        ->and(app(Gcego::class)->isBooted())->toBeTrue();
});

it('does not read env() at runtime', function (): void {
    $before = app(GcegoConfig::class)->name();
    putenv('GCEGO_STUDIO_NAME=Changed At Runtime');

    try {
        expect(app(GcegoConfig::class)->name())->toBe($before)
            ->and(app(Gcego::class)->name())->toBe($before);
    } finally {
        putenv('GCEGO_STUDIO_NAME');
    }

    foreach (glob(dirname(__DIR__, 2).'/src/{,*/}*.php', GLOB_BRACE) ?: [] as $file) {
        $calls = array_filter(
            token_get_all((string) file_get_contents($file)),
            fn (mixed $token): bool => is_array($token) && in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true) && ltrim($token[1], '\\') === 'env',
        );

        expect($calls)->toBe([], $file.' calls env()');
    }
});

it('works from a cached configuration array', function (): void {
    // config:cache stores the merged array; GCEGO only ever reads the repository.
    $cached = config('gcego');
    $this->rebuildApplication(is_array($cached) ? array_replace($cached, ['name' => 'Cached']) : []);

    expect(app(Gcego::class)->name())->toBe('Cached')
        ->and(app(Gcego::class)->isBooted())->toBeTrue();
});
