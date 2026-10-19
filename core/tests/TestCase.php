<?php

declare(strict_types=1);

namespace Gcego\Core\Tests;

use Gcego\Core\GcegoServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @var array<string, mixed>
     */
    private array $gcegoConfig = [];

    /**
     * @var list<class-string<ServiceProvider>>
     */
    private array $extraProviders = [];

    /**
     * Rebuild the application with config('gcego.*') overrides and extra providers,
     * applied before any provider boots.
     *
     * @param  array<string, mixed>  $config  Keys relative to "gcego", e.g. ['auto_boot' => false].
     * @param  list<class-string<ServiceProvider>>  $providers  Registered after GcegoServiceProvider.
     */
    protected function rebuildApplication(array $config = [], array $providers = []): Application
    {
        $this->gcegoConfig = $config;
        $this->extraProviders = $providers;
        $this->refreshApplication();

        /** @var Application */
        return $this->app;
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [GcegoServiceProvider::class, ...$this->extraProviders];
    }

    protected function defineEnvironment($app): void
    {
        foreach ($this->gcegoConfig as $key => $value) {
            $app['config']->set('gcego.'.$key, $value);
        }
    }
}
