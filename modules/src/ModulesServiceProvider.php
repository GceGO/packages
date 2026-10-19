<?php

declare(strict_types=1);

namespace Gcego\Modules;

use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Modules\Compilation\ModuleCompiler;
use Gcego\Modules\Context\ModuleContextResolver;
use Gcego\Modules\Diagnostics\ModuleInspector;
use Gcego\Modules\Extensions\ModuleExtensions;
use Gcego\Modules\Navigation\ModuleNavigationContributor;
use Gcego\Modules\Pages\ResourcePageFactory;
use Gcego\Modules\Registry\ModuleKind;
use Gcego\Modules\Relationships\ParentRecords;
use Gcego\Modules\Relationships\RelatedRecords;
use Gcego\Modules\Support\CurrentPageRoute;
use Gcego\Navigation\NavigationManager;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\RegistryConfig;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel integration for gcego/modules; also the "gcego/modules" GCEGO package.
 *
 * register(): configuration, services, the "module" registry kind and the
 * module discovery roots (added to gcego-registry's, so modules are found,
 * cached and lazily loaded by the registry). boot(): translations and the
 * navigation contributor. Routes are gcego/pages' (from the registry index);
 * booting never queries or loads a module.
 */
final class ModulesServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Name = 'gcego-modules';

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gcego-modules.php', self::Name);

        $this->app->singleton(ModulesConfig::class, static function (Application $app): ModulesConfig {
            $config = $app->make(Repository::class)->get(self::Name, []);

            return ModulesConfig::fromArray(is_array($config) ? $config : []);
        });

        foreach ([ModuleManager::class, ModuleExtensions::class, ResourcePageFactory::class, ModuleCompiler::class, ModuleInspector::class, CurrentPageRoute::class] as $singleton) {
            $this->app->singleton($singleton);
        }

        // Per request: they hold the current page's parent decisions and context.
        foreach ([ParentRecords::class, RelatedRecords::class, ModuleContextResolver::class, ModuleNavigationContributor::class] as $scoped) {
            $this->app->scoped($scoped);
        }

        // After resolution, not now: Laravel's package discovery may register this provider before gcego/registry binds DefinitionKinds.
        $this->callAfterResolving(DefinitionKinds::class, static fn (DefinitionKinds $kinds) => $kinds->register(new ModuleKind));

        // Just before gcego/registry reads its configuration (configuration is final by then).
        $this->app->beforeResolving(RegistryConfig::class, fn (): null => $this->addDiscoveryRoots());
    }

    public function boot(): void
    {
        $root = dirname(__DIR__);
        $this->loadTranslationsFrom($root.'/resources/lang', self::Name);
        $this->publishes([$root.'/config/gcego-modules.php' => $this->app->configPath('gcego-modules.php')], 'gcego-modules-config');

        if ($this->app->bound(NavigationManager::class)) {
            $this->app->make(NavigationManager::class)->contribute(ModuleNavigationContributor::class);
        }
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/modules',
            requires: ['gcego/core', 'gcego/registry', 'gcego/runtime', 'gcego/chroma', 'gcego/data', 'gcego/security', 'gcego/actions', 'gcego/forms', 'gcego/tables', 'gcego/infolists', 'gcego/widgets', 'gcego/layouts', 'gcego/navigation', 'gcego/pages'],
            capabilities: ['modules', 'modules.resources', 'modules.pages', 'modules.relationships', 'modules.navigation'],
            description: 'Declarative modules and resources that become authorized CRUD and relationship pages.',
        );
    }

    /**
     * Module folders become gcego/registry discovery roots of kind "module"
     * (once; configuration caching keeps them).
     */
    private function addDiscoveryRoots(): null
    {
        $config = $this->app->make(Repository::class);
        $modules = ModulesConfig::fromArray(is_array($settings = $config->get(self::Name, [])) ? $settings : []);

        if (! $modules->discovery) {
            return null;
        }

        $roots = $config->get('gcego-registry.discovery.roots', []);
        $roots = is_array($roots) ? $roots : [];

        foreach ($modules->paths as $path) {
            foreach ($roots as $root) {
                if (is_array($root) && ($root['kind'] ?? null) === ModuleKind::Id && ($root['path'] ?? null) === $path) {
                    continue 2;
                }
            }

            $roots[] = ['path' => $path, 'kind' => ModuleKind::Id, 'entrypoint' => $modules->entrypoint];
        }

        $config->set('gcego-registry.discovery.roots', $roots);

        return null;
    }
}
