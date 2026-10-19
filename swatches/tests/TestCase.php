<?php

declare(strict_types=1);

namespace Gcego\Swatches\Tests;

use Gcego\Core\GcegoServiceProvider;
use Gcego\Swatches\SwatchesServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @var array<string, mixed>
     */
    private array $overrides = [];

    /**
     * Boot a fresh application with these config values (full keys).
     *
     * @param  array<string, mixed>  $config
     */
    public function rebuildApplication(array $config = []): Application
    {
        $this->overrides = $config;
        Facade::clearResolvedInstances();
        $this->refreshApplication();

        /** @var Application */
        return $this->app;
    }

    protected function getPackageProviders($app): array
    {
        return [GcegoServiceProvider::class, SwatchesServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('gcego-swatches.cache.path', sys_get_temp_dir().'/gcego-swatches-test-'.getmypid().'.php');

        foreach ($this->overrides as $key => $value) {
            $app['config']->set($key, $value);
        }
    }
}
