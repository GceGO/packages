<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests\Fixtures\ModulesStandIn;

use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\RegistryConfig;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;

/**
 * Reproduces the registry wiring of Gcego\Modules\ModulesServiceProvider:
 * the "module" kind registered after DefinitionKinds resolves, and Module.php
 * discovery roots added just before RegistryConfig resolves, skipping a root
 * the registry configuration already lists for the same folder and kind.
 */
final class ModulesStandInServiceProvider extends ServiceProvider
{
    public const string Entrypoint = '#(^|/)Module\.php$#';

    public function register(): void
    {
        $this->callAfterResolving(DefinitionKinds::class, static fn (DefinitionKinds $kinds) => $kinds->register(new ModuleStandInKind));
        $this->app->beforeResolving(RegistryConfig::class, fn (): null => $this->addDiscoveryRoots());
    }

    private function addDiscoveryRoots(): null
    {
        $config = $this->app->make(Repository::class);
        $paths = $config->get('gcego-modules.discovery.paths', []);
        $roots = $config->get('gcego-registry.discovery.roots', []);
        $roots = is_array($roots) ? $roots : [];

        foreach (is_array($paths) ? $paths : [] as $path) {
            foreach ($roots as $root) {
                if (is_array($root) && ($root['kind'] ?? null) === ModuleStandInKind::Id && ($root['path'] ?? null) === $path) {
                    continue 2;
                }
            }

            $roots[] = ['path' => $path, 'kind' => ModuleStandInKind::Id, 'entrypoint' => self::Entrypoint];
        }

        $config->set('gcego-registry.discovery.roots', $roots);

        return null;
    }
}
