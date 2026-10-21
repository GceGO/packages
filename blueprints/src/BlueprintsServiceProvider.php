<?php

declare(strict_types=1);

namespace Gcego\Blueprints;

use Gcego\Blueprints\Registry\BlueprintKind;
use Gcego\Blueprints\Resolution\ReferenceResolver;
use Gcego\Blueprints\Validation\BlueprintValidator;
use Gcego\Blueprints\Validation\ReferenceRules;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\RegistryConfig;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel integration for gcego/blueprints, and the "gcego/blueprints" GCEGO
 * package (gcego/core discovers it).
 *
 * It owns the "blueprint" registry kind: it registers BlueprintKind when the
 * registry's DefinitionKinds is resolved, and adds its discovery roots just
 * before the registry reads its configuration, so provider order does not
 * matter. Nothing is discovered or loaded here.
 */
final class BlueprintsServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Name = 'gcego-blueprints';

    public const string ConfigTag = 'gcego-blueprints-config';

    /**
     * Environments in which reference validation may be turned off.
     */
    public const array UncheckedEnvironments = ['local', 'testing'];

    public function register(): void
    {
        $this->mergeConfigFrom(self::configPath(), self::Name);

        $this->app->singleton(ReferenceRules::class, static fn (): ReferenceRules => ReferenceRules::defaults());

        $this->app->singleton(BlueprintValidator::class, fn (Application $app): BlueprintValidator => new BlueprintValidator(
            $this->validatesReferences($app) ? $app->make(ReferenceRules::class) : new ReferenceRules,
        ));

        $this->app->singleton(ReferenceResolver::class, static fn (Application $app): ReferenceResolver => new ReferenceResolver(
            new BlueprintValidator($app->make(ReferenceRules::class)),
        ));

        $this->app->singleton(BlueprintKind::class, static fn (Application $app): BlueprintKind => new BlueprintKind($app->make(BlueprintValidator::class)));

        // After resolution, not now: the registry provider may register after this one.
        $this->callAfterResolving(DefinitionKinds::class, function (DefinitionKinds $kinds): void {
            $kinds->register($this->app->make(BlueprintKind::class));
        });

        // Just before gcego/registry reads its configuration (configuration is final by then).
        $this->app->beforeResolving(RegistryConfig::class, fn (): null => $this->addDiscoveryRoots());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([self::configPath() => $this->app->configPath('gcego-blueprints.php')], self::ConfigTag);
        }
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/blueprints',
            requires: ['gcego/core', 'gcego/registry'],
            capabilities: ['blueprints'],
            description: 'Declarative, validated feature definitions.',
        );
    }

    public static function configPath(): string
    {
        return dirname(__DIR__).'/config/gcego-blueprints.php';
    }

    /**
     * Reference validation is always on, except where explicitly turned off
     * in an environment listed in UncheckedEnvironments.
     */
    private function validatesReferences(Application $app): bool
    {
        return $app->make(Repository::class)->get(self::Name.'.validate_references', true) !== false
            || ! $app->environment(self::UncheckedEnvironments);
    }

    /**
     * Blueprint folders become gcego/registry discovery roots of kind
     * "blueprint", unless the registry configuration already lists the same
     * folder for that kind (configuration caching keeps them).
     */
    private function addDiscoveryRoots(): null
    {
        $config = $this->app->make(Repository::class);

        if ($config->get(self::Name.'.discovery.enabled', true) === false) {
            return null;
        }

        $paths = $config->get(self::Name.'.discovery.paths', []);
        $entrypoint = $config->get(self::Name.'.discovery.entrypoint');
        $roots = $config->get('gcego-registry.discovery.roots', []);
        $roots = is_array($roots) ? $roots : [];

        foreach (is_array($paths) ? $paths : [] as $path) {
            foreach ($roots as $root) {
                if (is_array($root) && ($root['kind'] ?? null) === BlueprintKind::Id && ($root['path'] ?? null) === $path) {
                    continue 2;
                }
            }

            $roots[] = ['path' => $path, 'kind' => BlueprintKind::Id, 'entrypoint' => is_string($entrypoint) ? $entrypoint : 'directory'];
        }

        $config->set('gcego-registry.discovery.roots', $roots);

        return null;
    }
}
