<?php

declare(strict_types=1);

namespace Gcego\Chroma\Tests;

use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\ChromaServiceProvider;
use Gcego\Chroma\Slots\SlotDefinition;
use Gcego\Chroma\Tailwind;
use Gcego\Chroma\Tests\Fixtures\CardRenderer;
use Gcego\Chroma\Tests\Fixtures\ExampleCard;
use Gcego\Core\GcegoServiceProvider;
use Gcego\Registry\RegistryServiceProvider;
use Gcego\Runtime\Rendering\RendererRegistry;
use Gcego\Runtime\RuntimeServiceProvider;
use Illuminate\Foundation\Application;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @var array<string, mixed>
     */
    private array $overrides = [];

    protected function setUp(): void
    {
        $this->afterApplicationCreated(fn () => $this->registerFixtures());

        parent::setUp();
    }

    /**
     * @param  array<string, mixed>  $config  Full config keys.
     */
    public function rebuildApplication(array $config = []): Application
    {
        $this->overrides = $config;
        $this->refreshApplication();
        $this->registerFixtures();

        /** @var Application */
        return $this->app;
    }

    protected function getPackageProviders($app): array
    {
        return [LivewireServiceProvider::class, GcegoServiceProvider::class, RegistryServiceProvider::class, RuntimeServiceProvider::class, ChromaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('gcego-registry.discovery.enabled', false);
        $app['config']->set('gcego-registry.cache.enabled', false);
        $app['view']->addNamespace('fixtures', __DIR__.'/Fixtures/views');

        foreach ($this->overrides as $key => $value) {
            $app['config']->set($key, $value);
        }
    }

    /**
     * What a future component package does in its service provider.
     */
    private function registerFixtures(): void
    {
        $app = $this->app;

        if ($app === null || $app->make(RendererRegistry::class)->has(ExampleCard::class)) {
            return;
        }

        $app->make(RendererRegistry::class)->register(ExampleCard::class, CardRenderer::class);
        $app->make(ChromaManager::class)->defineSlots(ExampleCard::class, [
            new SlotDefinition(Tailwind::Wrapper, 'surface.card'),
            new SlotDefinition(Tailwind::Header, 'layout.header', ['border-b', 'border-gcego-border', 'px-6', 'py-4']),
            new SlotDefinition(Tailwind::Title, 'typography.heading'),
            new SlotDefinition(Tailwind::Body, classes: ['p-6']),
        ]);
    }
}
