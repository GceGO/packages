<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests;

use Gcego\Core\GcegoServiceProvider;
use Gcego\Registry\RegistryServiceProvider;
use Gcego\Runtime\Contracts\StylingResolver;
use Gcego\Runtime\Rendering\RendererRegistry;
use Gcego\Runtime\RuntimeServiceProvider;
use Gcego\Runtime\Tests\Fixtures\CountingRenderer;
use Gcego\Runtime\Tests\Fixtures\ExampleContainer;
use Gcego\Runtime\Tests\Fixtures\ExampleHeading;
use Gcego\Runtime\Tests\Fixtures\ExampleParagraph;
use Gcego\Runtime\Tests\Fixtures\ExampleSearch;
use Gcego\Runtime\Tests\Fixtures\ExampleTable;
use Gcego\Runtime\Tests\Fixtures\FailingComponent;
use Gcego\Runtime\Tests\Fixtures\FailingRenderer;
use Gcego\Runtime\Tests\Fixtures\FixtureStylingResolver;
use Gcego\Runtime\Tests\Fixtures\HeadingRenderer;
use Gcego\Runtime\Tests\Fixtures\ListContainerRenderer;
use Gcego\Runtime\Tests\Fixtures\MissingViewComponent;
use Gcego\Runtime\Tests\Fixtures\MissingViewRenderer;
use Gcego\Runtime\Tests\Fixtures\ParagraphRenderer;
use Gcego\Runtime\Tests\Fixtures\SearchRenderer;
use Gcego\Runtime\Tests\Fixtures\TableRenderer;
use Gcego\Runtime\Tests\Fixtures\TrustedHtmlComponent;
use Gcego\Runtime\Tests\Fixtures\TrustedHtmlRenderer;
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
        CountingRenderer::$instances = 0;
        $this->afterApplicationCreated(fn () => $this->registerFixtureRenderers());

        parent::setUp();
    }

    /**
     * @param  array<string, mixed>  $config  Full config keys, e.g. ['gcego-runtime.rendering.strict' => false].
     */
    public function rebuildApplication(array $config = []): Application
    {
        $this->overrides = $config;
        $this->refreshApplication();
        $this->registerFixtureRenderers();

        /** @var Application */
        return $this->app;
    }

    /**
     * Rebuild with the fixture styling resolver bound before renderers are created.
     */
    public function rebuildApplicationWithStyling(): Application
    {
        $this->afterApplicationCreated(fn () => $this->app?->singleton(StylingResolver::class, FixtureStylingResolver::class));
        $this->refreshApplication();
        $this->app?->singleton(StylingResolver::class, FixtureStylingResolver::class);
        $this->registerFixtureRenderers();

        /** @var Application */
        return $this->app;
    }

    protected function getPackageProviders($app): array
    {
        return [LivewireServiceProvider::class, GcegoServiceProvider::class, RegistryServiceProvider::class, RuntimeServiceProvider::class];
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

    private function registerFixtureRenderers(): void
    {
        $renderers = $this->app?->make(RendererRegistry::class);

        if ($renderers === null || $renderers->has(ExampleHeading::class)) {
            return;
        }

        $renderers->register(ExampleHeading::class, HeadingRenderer::class);
        $renderers->register(ExampleParagraph::class, ParagraphRenderer::class);
        $renderers->register(ExampleContainer::class, ListContainerRenderer::class);
        $renderers->register(ExampleTable::class, TableRenderer::class);
        $renderers->register(ExampleSearch::class, SearchRenderer::class);
        $renderers->register(FailingComponent::class, FailingRenderer::class);
        $renderers->register(TrustedHtmlComponent::class, TrustedHtmlRenderer::class);
        $renderers->register(MissingViewComponent::class, MissingViewRenderer::class);
    }
}
