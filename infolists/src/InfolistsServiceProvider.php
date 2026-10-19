<?php

declare(strict_types=1);

namespace Gcego\Infolists;

use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Slots\SlotDefinition;
use Gcego\Chroma\Tailwind;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Infolists\Diagnostics\InfolistInspector;
use Gcego\Infolists\Entries\Entry;
use Gcego\Infolists\Layout\Grid;
use Gcego\Infolists\Layout\LayoutComponent;
use Gcego\Infolists\Layout\Section;
use Gcego\Infolists\Livewire\InfolistHost;
use Gcego\Infolists\Presets\InfolistPresets;
use Gcego\Infolists\Rendering\EntryRenderer;
use Gcego\Infolists\Rendering\InfolistRenderContext;
use Gcego\Infolists\Rendering\InfolistRenderer;
use Gcego\Infolists\Rendering\LayoutRenderer;
use Gcego\Runtime\Rendering\RendererRegistry;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Laravel integration for gcego/infolists; also the "gcego/infolists" GCEGO package.
 *
 * Singletons hold configuration and compiled schemas (immutable); the
 * render context and inspector are scoped. Booting registers renderers,
 * the Livewire host, views, translations, the anonymous Blade component
 * and styling slots; it never queries or loads definitions.
 */
final class InfolistsServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Views = 'gcego-infolists';

    public const string LivewireAlias = 'gcego-infolist';

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gcego-infolists.php', 'gcego-infolists');

        $this->app->singleton(InfolistsConfig::class, static function (Application $app): InfolistsConfig {
            $config = $app->make(Repository::class)->get('gcego-infolists', []);

            return InfolistsConfig::fromArray(is_array($config) ? $config : []);
        });
        $this->app->singleton(InfolistManager::class);
        $this->app->scoped(InfolistRenderContext::class);
        $this->app->scoped(InfolistInspector::class);
    }

    public function boot(): void
    {
        $root = dirname(__DIR__);
        $this->loadViewsFrom($root.'/resources/views', self::Views);
        $this->loadTranslationsFrom($root.'/resources/lang', self::Views);
        Blade::anonymousComponentPath($root.'/resources/views/components', self::Views);
        $this->publishes([$root.'/config/gcego-infolists.php' => $this->app->configPath('gcego-infolists.php')], 'gcego-infolists-config');
        $this->publishes([$root.'/resources/views' => $this->app->resourcePath('views/vendor/'.self::Views)], 'gcego-infolists-views');

        $this->app->make(InfolistsConfig::class);

        Livewire::component(self::LivewireAlias, InfolistHost::class);

        $renderers = $this->app->make(RendererRegistry::class);
        $renderers->register(Infolist::class, InfolistRenderer::class);
        $renderers->register(Entry::class, EntryRenderer::class);
        $renderers->register(LayoutComponent::class, LayoutRenderer::class);

        $styling = $this->app->make(ChromaManager::class);
        $styling->defineSlots(Infolist::class, [
            new SlotDefinition(Tailwind::Wrapper, classes: InfolistPresets::Wrapper),
            new SlotDefinition(Tailwind::Header, classes: InfolistPresets::Header),
            new SlotDefinition(Tailwind::Title, classes: InfolistPresets::Title),
            new SlotDefinition(Tailwind::Description, classes: InfolistPresets::Description),
            new SlotDefinition(Tailwind::Body, classes: InfolistPresets::Body),
            new SlotDefinition(Infolist::EmptyState, classes: InfolistPresets::EmptyState),
        ]);
        $styling->defineSlots(Section::class, [
            new SlotDefinition(Tailwind::Wrapper, classes: InfolistPresets::Section),
            new SlotDefinition(Tailwind::Header, classes: InfolistPresets::SectionHeader),
            new SlotDefinition(Tailwind::Description, classes: InfolistPresets::Description),
            new SlotDefinition(Tailwind::Body, classes: InfolistPresets::SectionBody),
        ]);
        $styling->defineSlots(LayoutComponent::class, [new SlotDefinition(Tailwind::Wrapper)]);
        $styling->defineSlots(Grid::class, [new SlotDefinition(Tailwind::Wrapper)]);
        $styling->defineSlots(Entry::class, [
            new SlotDefinition(Tailwind::Wrapper, classes: InfolistPresets::Entry),
            new SlotDefinition(Tailwind::Label, classes: InfolistPresets::EntryLabel),
            new SlotDefinition(Entry::Value, classes: InfolistPresets::EntryValue),
            new SlotDefinition(Tailwind::Description, classes: InfolistPresets::EntryDescription),
        ]);
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/infolists',
            requires: ['gcego/core', 'gcego/runtime', 'gcego/chroma', 'gcego/data', 'gcego/security', 'gcego/actions', 'gcego/tables'],
            capabilities: ['infolists', 'infolists.livewire', 'infolists.relationships', 'infolists.authorization'],
            description: 'Declarative, authorized record details with entries, relationships and layouts.',
        );
    }
}
