<?php

declare(strict_types=1);

namespace Gcego\Tables;

use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Slots\SlotDefinition;
use Gcego\Chroma\Tailwind;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Rendering\Renderers\RendererRegistry;
use Gcego\Tables\Actions\TableActions;
use Gcego\Tables\Columns\Column;
use Gcego\Tables\Contracts\PreferenceStore;
use Gcego\Tables\Contracts\SavedViewRepository;
use Gcego\Tables\Diagnostics\TableInspector;
use Gcego\Tables\Export\CsvWriter;
use Gcego\Tables\Filters\RelationshipOptions;
use Gcego\Tables\Filters\TableFilter;
use Gcego\Tables\Livewire\TableHost;
use Gcego\Tables\Preferences\ColumnPreferences;
use Gcego\Tables\Preferences\DatabasePreferenceStore;
use Gcego\Tables\Presets\TablePresets;
use Gcego\Tables\Query\EffectiveSources;
use Gcego\Tables\Rendering\ColumnRenderer;
use Gcego\Tables\Rendering\FilterRenderer;
use Gcego\Tables\Rendering\TableRenderContext;
use Gcego\Tables\Rendering\TableRenderer;
use Gcego\Tables\Rendering\TableRows;
use Gcego\Tables\Support\Icons;
use Gcego\Tables\Views\DatabaseSavedViewRepository;
use Gcego\Tables\Views\SavedViewManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Laravel integration for gcego/tables; also the "gcego/tables" GCEGO package.
 *
 * Singletons hold configuration, compiled schemas and derived sources
 * (immutable definitions) and the icon set (filled at boot). Services that
 * touch the current user or request are scoped. Booting registers
 * renderers, the Livewire host, views, translations and styling slots; it
 * never queries, loads definitions or runs migrations.
 */
final class TablesServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Views = 'gcego-tables';

    public const string LivewireAlias = 'gcego-table';

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gcego-tables.php', 'gcego-tables');

        $this->app->singleton(TablesConfig::class, static function (Application $app): TablesConfig {
            $config = $app->make(Repository::class)->get('gcego-tables', []);

            return TablesConfig::fromArray(is_array($config) ? $config : []);
        });
        $this->app->singleton(TableManager::class);
        $this->app->singleton(EffectiveSources::class);
        $this->app->singleton(Icons::class);
        $this->app->scoped(TableRenderContext::class);
        $this->app->scoped(TableActions::class);
        $this->app->scoped(TableRows::class);
        $this->app->scoped(TableInspector::class);
        $this->app->scoped(ColumnPreferences::class);
        $this->app->scoped(SavedViewManager::class);
        $this->app->scoped(CsvWriter::class);
        $this->app->scoped(RelationshipOptions::class);
        $this->app->bindIf(PreferenceStore::class, DatabasePreferenceStore::class, true);
        $this->app->bindIf(SavedViewRepository::class, DatabaseSavedViewRepository::class, true);
    }

    public function boot(): void
    {
        $root = dirname(__DIR__);
        $this->loadViewsFrom($root.'/resources/views', self::Views);
        $this->loadTranslationsFrom($root.'/resources/lang', self::Views);
        $this->publishes([$root.'/config/gcego-tables.php' => $this->app->configPath('gcego-tables.php')], 'gcego-tables-config');
        $this->publishes([$root.'/resources/views' => $this->app->resourcePath('views/vendor/'.self::Views)], 'gcego-tables-views');
        $this->publishesMigrations([$root.'/database/migrations' => $this->app->databasePath('migrations')], 'gcego-tables-migrations');

        $this->app->make(TablesConfig::class);

        Livewire::component(self::LivewireAlias, TableHost::class);

        $renderers = $this->app->make(RendererRegistry::class);
        $renderers->register(Table::class, TableRenderer::class);
        $renderers->register(Column::class, ColumnRenderer::class);
        $renderers->register(TableFilter::class, FilterRenderer::class);

        $styling = $this->app->make(ChromaManager::class);
        $styling->defineSlots(Table::class, [
            new SlotDefinition(Tailwind::Wrapper, classes: TablePresets::Wrapper),
            new SlotDefinition(Tailwind::Header, classes: TablePresets::Header),
            new SlotDefinition(Table::Toolbar, classes: TablePresets::Toolbar),
            new SlotDefinition(Table::SearchInput, classes: TablePresets::Search),
            new SlotDefinition(Table::Filters, classes: TablePresets::Filters),
            new SlotDefinition(Table::TableElement, 'data.table', TablePresets::Table),
            new SlotDefinition(Table::TableHead, 'data.table.head', TablePresets::TableHead),
            new SlotDefinition(Table::HeaderCell, 'data.table.header-cell', TablePresets::HeaderCell),
            new SlotDefinition(Table::TableBody, classes: TablePresets::TableBody),
            new SlotDefinition(Table::Row, 'data.table.row', TablePresets::Row),
            new SlotDefinition(Table::Cell, 'data.table.cell', TablePresets::Cell),
            new SlotDefinition(Tailwind::Footer, classes: TablePresets::Footer),
            new SlotDefinition(Table::Pagination, classes: TablePresets::Pagination),
            new SlotDefinition(Table::EmptyState, classes: TablePresets::EmptyState),
        ]);
        $styling->defineSlots(Column::class, [
            new SlotDefinition(Table::HeaderCell, 'data.table.header-cell', TablePresets::HeaderCell),
        ]);
        $styling->defineSlots(TableFilter::class, [new SlotDefinition(Tailwind::Wrapper)]);
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/tables',
            requires: ['gcego/core', 'gcego/rendering', 'gcego/chroma', 'gcego/data', 'gcego/security', 'gcego/actions'],
            capabilities: ['tables', 'tables.livewire', 'tables.preferences', 'tables.saved-views', 'tables.export'],
            description: 'Declarative data tables with search, filters, sorting, pagination, selection, actions, preferences and saved views.',
        );
    }
}
