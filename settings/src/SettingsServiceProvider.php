<?php

declare(strict_types=1);

namespace Gcego\Settings;

use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Slots\SlotDefinition;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Notifications\Definitions\NotificationCategory;
use Gcego\Notifications\NotificationManager;
use Gcego\Panels\PanelManager;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\RegistryConfig;
use Gcego\Settings\Approvals\SettingApprovals;
use Gcego\Settings\Approvals\SettingRequest;
use Gcego\Settings\Approvals\SettingRequestPolicy;
use Gcego\Settings\Cache\SettingsCache;
use Gcego\Settings\Cache\SettingsCacheKeyResolver;
use Gcego\Settings\Console\ClearSettingsCacheCommand;
use Gcego\Settings\Console\InspectSettingsCommand;
use Gcego\Settings\Contracts\SettingsAuthorizer;
use Gcego\Settings\Contracts\SettingsRepository;
use Gcego\Settings\Contracts\SettingValidator;
use Gcego\Settings\Definitions\SettingsDefinitions;
use Gcego\Settings\Diagnostics\SettingsInspector;
use Gcego\Settings\History\SettingsHistoryRepository;
use Gcego\Settings\Integration\FormSettingsAdapter;
use Gcego\Settings\Integration\PanelSettingsExtension;
use Gcego\Settings\Integration\SettingsNotifier;
use Gcego\Settings\Livewire\SettingsForm;
use Gcego\Settings\Livewire\SettingsPanel;
use Gcego\Settings\Pages\SettingsPageResolver;
use Gcego\Settings\Persistence\EloquentSettingsRepository;
use Gcego\Settings\Registry\SettingsKind;
use Gcego\Settings\Registry\SettingsPageKind;
use Gcego\Settings\Resolution\SettingsReader;
use Gcego\Settings\Scopes\ScopePrecedenceResolver;
use Gcego\Settings\Scopes\ScopeResolver;
use Gcego\Settings\Search\SettingsSearch;
use Gcego\Settings\Security\SettingAccessPolicy;
use Gcego\Settings\Security\SettingEncryption;
use Gcego\Settings\Support\SettingsLogger;
use Gcego\Settings\Support\SettingsStyles;
use Gcego\Settings\Transfer\SettingsExporter;
use Gcego\Settings\Transfer\SettingsImporter;
use Gcego\Settings\Types\SettingCaster;
use Gcego\Settings\Types\SettingSerializer;
use Gcego\Settings\Validation\SettingsValidator;
use Gcego\Settings\Writing\SettingsWriter;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Laravel integration for gcego/settings; also the "gcego/settings" GCEGO package.
 *
 * register(): configuration, services and the "settings" and
 * "settings-page" registry kinds with their discovery roots (no queries,
 * no definitions loaded, no cache or database access).
 * boot(): views, translations, migrations, Livewire components
 * (gcego-settings-form, gcego-settings-panel), styling slots, the
 * notification category, the panel extension, the SettingRequest policy
 * and the console commands.
 *
 * Services that read the request (user, tenant, panel) or keep request
 * memory are scoped: Laravel forgets them after every request and queued
 * job, Octane included, so no context leaks between tenants or users.
 */
final class SettingsServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Name = 'gcego-settings';

    /** Styling slots of settings views (ChromaManager::override(SettingsGroup::class, ...)). */
    public const array Slots = [
        'wrapper' => 'The settings screen.', 'sidebar' => 'Search and navigation.', 'navigation' => 'The list of pages.',
        'group' => 'The form of a page.', 'section' => 'A section of settings.', 'field' => 'One setting.',
        'description' => 'Descriptions.', 'scope' => 'The scope being edited.', 'inherited' => 'Inherited and default badges.',
        'override' => 'Override badges.', 'actions' => 'Save and reset actions.', 'history' => 'The history.',
        'empty' => 'Empty states.', 'loading' => 'Loading states.', 'error' => 'Errors.',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gcego-settings.php', self::Name);

        $this->app->singleton(SettingsConfig::class, static function (Application $app): SettingsConfig {
            $config = $app->make(Repository::class)->get(self::Name, []);

            return SettingsConfig::fromArray(is_array($config) ? $config : []);
        });

        // Stateless, or immutable definitions: one per process.
        foreach ([SettingsDefinitions::class, SettingsCacheKeyResolver::class, SettingCaster::class, SettingSerializer::class, ScopePrecedenceResolver::class, SettingEncryption::class, SettingsLogger::class, SettingsStyles::class, FormSettingsAdapter::class] as $singleton) {
            $this->app->singleton($singleton);
        }

        $this->app->singleton(SettingsRepository::class, EloquentSettingsRepository::class);

        // Per request (or queue job): they read the current user, tenant and panel, or keep request memory.
        $this->app->scoped(SettingsAuthorizer::class, SettingAccessPolicy::class);
        $this->app->scoped(SettingValidator::class, SettingsValidator::class);

        foreach ([SettingsCache::class, ScopeResolver::class, SettingsReader::class, SettingsWriter::class, SettingsManager::class, SettingApprovals::class, SettingsNotifier::class, SettingsHistoryRepository::class, SettingsPageResolver::class, SettingsSearch::class, SettingsExporter::class, SettingsImporter::class, SettingsInspector::class] as $scoped) {
            $this->app->scoped($scoped);
        }

        // After resolution, not now: Laravel's package discovery may register this provider before gcego/registry binds DefinitionKinds.
        $this->callAfterResolving(DefinitionKinds::class, static function (DefinitionKinds $kinds): void {
            $kinds->register(new SettingsKind);
            $kinds->register(new SettingsPageKind);
        });

        // Configuration is final by the time this is first resolved (Testbench sets it after register()).
        $this->app->beforeResolving(RegistryConfig::class, fn (): null => $this->addDiscoveryRoots());
    }

    public function boot(): void
    {
        $root = dirname(__DIR__);
        $this->loadViewsFrom($root.'/resources/views', self::Name);
        $this->loadTranslationsFrom($root.'/resources/lang', self::Name);
        $this->loadMigrationsFrom($root.'/database/migrations');
        $this->publishes([$root.'/config/gcego-settings.php' => $this->app->configPath('gcego-settings.php')], 'gcego-settings-config');
        $this->publishes([$root.'/database/migrations' => $this->app->databasePath('migrations')], 'gcego-settings-migrations');
        $this->publishes([$root.'/resources/views' => $this->app->resourcePath('views/vendor/gcego-settings')], 'gcego-settings-views');

        $config = $this->app->make(SettingsConfig::class);

        if ($config->enabled) {
            Livewire::component('gcego-settings-form', SettingsForm::class);
            Livewire::component('gcego-settings-panel', SettingsPanel::class);
        }

        $slots = [];

        foreach (self::Slots as $slot => $description) {
            $slots[] = new SlotDefinition($slot, description: $description);
        }

        $this->app->make(ChromaManager::class)->defineSlots(SettingsGroup::class, $slots, inherit: true);

        if ($this->app->bound(NotificationManager::class)) {
            $this->app->make(NotificationManager::class)->category(new NotificationCategory(SettingsNotifier::Category, 'gcego-settings::messages.category'));
        }

        if ($config->enabled && $this->app->bound(PanelManager::class)) {
            $this->app->make(PanelManager::class)->extend(PanelSettingsExtension::class);
        }

        $gate = $this->app->make(Gate::class);

        if ($gate->getPolicyFor(SettingRequest::class) === null) {
            $gate->policy(SettingRequest::class, SettingRequestPolicy::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([InspectSettingsCommand::class, ClearSettingsCacheCommand::class]);
        }
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/settings',
            requires: ['gcego/core', 'gcego/registry', 'gcego/rendering', 'gcego/chroma', 'gcego/security', 'gcego/actions', 'gcego/forms', 'gcego/panels', 'gcego/notifications', 'gcego/media', 'gcego/workflows'],
            capabilities: ['settings', 'settings.scopes', 'settings.encryption', 'settings.history', 'settings.pages', 'settings.transfer', 'settings.approvals', 'settings.preferences'],
            description: 'Declarative typed settings with scopes, encryption, validation, caching, history and generated settings pages.',
        );
    }

    private function addDiscoveryRoots(): null
    {
        $config = $this->app->make(Repository::class);

        if ($config->get(self::Name.'.enabled', true) !== true) {
            return null;
        }

        $roots = $config->get('gcego-registry.discovery.roots', []);
        $roots = is_array($roots) ? $roots : [];

        foreach ([SettingsKind::Id => self::Name.'.discovery.paths', SettingsPageKind::Id => self::Name.'.discovery.pages'] as $kind => $option) {
            $paths = $config->get($option, []);

            foreach (is_array($paths) ? $paths : [$paths] as $path) {
                foreach ($roots as $root) {
                    if (! is_string($path) || (is_array($root) && ($root['kind'] ?? null) === $kind && ($root['path'] ?? null) === $path)) {
                        continue 2;
                    }
                }

                $roots[] = ['path' => $path, 'kind' => $kind, 'entrypoint' => 'file'];
            }
        }

        $config->set('gcego-registry.discovery.roots', $roots);

        return null;
    }
}
