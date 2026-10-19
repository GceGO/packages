<?php

declare(strict_types=1);

namespace Gcego\Testing;

use Gcego\Testing\Contracts\TestEnvironment;
use Gcego\Testing\Diagnostics\TestingInspector;
use Gcego\Testing\Environment\TestingEnvironment;
use Gcego\Testing\Environment\TestStateResetter;
use Gcego\Testing\Fixtures\FixtureManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the toolkit's services as lazy singletons. Nothing is booted,
 * no route, listener or migration is added, and no database is touched:
 * with the package installed as a dev dependency nothing runs in a
 * request. Set gcego-testing.enabled to false to register nothing at all.
 */
final class TestingServiceProvider extends ServiceProvider
{
    public const string Name = 'gcego-testing';

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gcego-testing.php', self::Name);

        $this->app->singleton(TestingConfig::class, static function (Application $app): TestingConfig {
            $config = $app->make('config')->get(self::Name, []);

            return TestingConfig::fromArray(is_array($config) ? $config : []);
        });

        if ($this->app->make('config')->get(self::Name.'.enabled', true) === false) {
            return;
        }

        $this->app->singleton(TestEnvironment::class, static fn (Application $app): TestEnvironment => new TestingEnvironment($app));
        $this->app->singleton(TestingManager::class, static fn (Application $app): TestingManager => new TestingManager($app, $app->make(TestingConfig::class)));
        $this->app->singleton(FixtureManager::class, static function (Application $app): FixtureManager {
            $config = $app->make(TestingConfig::class);

            return new FixtureManager($app, $config->fixturesDirectory, $config->userProvider, $config->tenantProvider);
        });
        $this->app->singleton(TestStateResetter::class, static fn (Application $app): TestStateResetter => new TestStateResetter($app, $app->make(TestingConfig::class)->isolation));
        $this->app->singleton(TestingInspector::class, static fn (Application $app): TestingInspector => new TestingInspector($app, $app->make(TestingConfig::class)));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([dirname(__DIR__).'/config/gcego-testing.php' => $this->app->configPath('gcego-testing.php')], self::Name.'-config');
        }
    }
}
