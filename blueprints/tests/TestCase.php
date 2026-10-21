<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests;

use Gcego\Blueprints\BlueprintsServiceProvider;
use Gcego\Blueprints\Definitions\DefinitionCatalog;
use Gcego\Blueprints\Tests\Fixtures\Workspace;
use Gcego\Core\GcegoServiceProvider;
use Gcego\Registry\RegistryServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected Workspace $workspace;

    /**
     * @var array<string, mixed>
     */
    private array $overrides = [];

    /**
     * @var list<class-string>|null
     */
    private ?array $providers = null;

    protected function setUp(): void
    {
        $this->workspace = new Workspace;

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->workspace->remove();
        DefinitionCatalog::reset();
    }

    /**
     * Rebuild the application with configuration overrides (full keys, such as
     * "gcego-blueprints.validate_references"; "env" sets the environment).
     *
     * @param  array<string, mixed>  $config
     * @param  list<class-string>|null  $providers  Service providers in registration order (default: core, registry, blueprints).
     */
    protected function rebuildApplication(array $config = [], ?array $providers = null): Application
    {
        $this->overrides = $config;
        $this->providers = $providers;
        $this->refreshApplication();

        /** @var Application */
        return $this->app;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<class-string>|null  $providers
     */
    public function rebuildApplicationPublic(array $config = [], ?array $providers = null): Application
    {
        return $this->rebuildApplication($config, $providers);
    }

    protected function getPackageProviders($app): array
    {
        return $this->providers ?? [GcegoServiceProvider::class, RegistryServiceProvider::class, BlueprintsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Only the roots packages contribute (gcego/blueprints adds kind "blueprint" for its paths).
        $app['config']->set('gcego-registry.discovery.roots', []);
        $app['config']->set('gcego-blueprints.discovery.paths', [$this->workspace->path('Modules')]);
        $app['config']->set('gcego-registry.cache.enabled', false);
        $app['config']->set('gcego-registry.cache.path', $this->workspace->path('cache/gcego-registry.php'));

        foreach ($this->overrides as $key => $value) {
            if ($key === 'env') {
                $app['env'] = $value;

                continue;
            }

            $app['config']->set($key, $value);
        }
    }
}
