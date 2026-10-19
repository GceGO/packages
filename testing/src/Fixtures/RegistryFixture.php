<?php

declare(strict_types=1);

namespace Gcego\Testing\Fixtures;

use Gcego\Blueprints\Contracts\BlueprintResolver;
use Gcego\Registry\Cache\FileRegistryCache;
use Gcego\Registry\Discovery\DefinitionDiscovery;
use Gcego\Registry\Loading\PhpDefinitionLoader;
use Gcego\Registry\Registry;
use Gcego\Registry\RegistryConfig;
use Gcego\Registry\RegistryManager;
use Gcego\Registry\Resolution\DefinitionResolver;
use Gcego\Registry\Resolution\RegistryBlueprintResolver;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;

/**
 * Definition files discovered from a temporary workspace instead of the
 * application's app/GCEGo directories: the application's own definitions
 * are never read or changed, and the files disappear on cleanup.
 *
 *   $registry = GcegoTest::fixtures()->registry()
 *       ->definition('Modules/Subjects/Blueprint.php', "new Blueprint('subjects')", [Blueprint::class])
 *       ->root('Modules', 'blueprint', 'directory')
 *       ->apply(app());
 */
final class RegistryFixture
{
    /** @var list<array{path: string, kind: string, entrypoint: string, scope?: string}> */
    private array $roots = [];

    public function __construct(private readonly Workspace $workspace) {}

    /**
     * @param  list<class-string>  $imports
     */
    public function definition(string $relative, string $expression, array $imports = []): self
    {
        $this->workspace->php($relative, $expression, $imports);

        return $this;
    }

    /**
     * Discovers $kind definitions under a workspace directory. "directory"
     * treats each sub-directory as one definition; "file" each PHP file.
     */
    public function root(string $directory, string $kind = 'blueprint', string $entrypoint = 'file', ?string $scope = null): self
    {
        $root = ['path' => $this->workspace->directory($directory), 'kind' => $kind, 'entrypoint' => $entrypoint];

        if ($scope !== null) {
            $root['scope'] = $scope;
        }

        $this->roots[] = $root;

        return $this;
    }

    /**
     * The gcego-registry configuration that discovers only the workspace
     * (configured definition providers are switched off too).
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return [
            'gcego-registry.discovery.enabled' => true,
            'gcego-registry.discovery.roots' => $this->roots,
            'gcego-registry.discovery.ignore' => [],
            'gcego-registry.providers' => [],
            'gcego-registry.cache.enabled' => false,
            'gcego-registry.cache.path' => $this->workspace->path('cache/gcego-registry.php'),
        ];
    }

    /**
     * Applies the configuration and rebuilds the registry services so the
     * next lookup discovers the workspace. Services that captured the old
     * registry (built before this call) keep it: apply it before using
     * them, or in defineEnvironment().
     */
    public function apply(Container $container): void
    {
        $config = $container->make(Repository::class);

        foreach ($this->config() as $key => $value) {
            $config->set($key, $value);
        }

        foreach ([RegistryConfig::class, DefinitionDiscovery::class, PhpDefinitionLoader::class, FileRegistryCache::class, DefinitionResolver::class, RegistryManager::class, Registry::class, RegistryBlueprintResolver::class, BlueprintResolver::class] as $service) {
            $container->forgetInstance($service);
        }
    }

    public function workspace(): Workspace
    {
        return $this->workspace;
    }
}
