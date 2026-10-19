<?php

declare(strict_types=1);

namespace Gcego\Cli\Generation;

use Gcego\Cli\CliConfig;
use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Registry\RegistryConfig;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;

/**
 * Where generated definitions go: the folder the owning package discovers,
 * so a generated file is found without registration code. gcego-cli.paths
 * can override a kind (a warning is given when the override is not
 * discovered).
 */
final readonly class DefinitionPaths
{
    /** kind => the package configuration listing its discovery folders. */
    private const array PackagePaths = [
        'module' => 'gcego-modules.discovery.paths',
        'panel' => 'gcego-panels.discovery.paths',
        'workflow' => 'gcego-workflows.discovery.paths',
        'settings' => 'gcego-settings.discovery.paths',
        'analytics' => 'gcego-analytics.discovery.paths',
        'settings-page' => 'gcego-settings.discovery.pages',
    ];

    public function __construct(private CliConfig $cli, private Repository $config, private Container $container, private PathResolver $paths) {}

    /**
     * @throws GenerationException when nothing discovers this kind
     */
    public function directory(string $kind): string
    {
        $directory = $this->cli->paths[$kind] ?? $this->discovered($kind)[0] ?? null;

        if ($directory === null) {
            throw GenerationException::missing(sprintf('No folder is configured for "%s" definitions: add one to gcego-cli.paths.%s, or install the package that discovers them.', $kind, $kind));
        }

        return $this->paths->resolve($directory);
    }

    /**
     * Whether a file in $directory would be discovered as a $kind definition.
     */
    public function isDiscovered(string $kind, string $directory): bool
    {
        foreach ($this->discovered($kind) as $root) {
            $root = rtrim($root, '/');

            if ($directory === $root || str_starts_with($directory, $root.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The registry entrypoint rule of $kind's first root ("file", "directory" or a pattern).
     */
    public function entrypoint(string $kind): string
    {
        foreach ($this->roots() as $root) {
            if (($root['kind'] ?? null) === $kind && is_string($root['entrypoint'] ?? null)) {
                return $root['entrypoint'];
            }
        }

        return 'file';
    }

    /**
     * @return list<string>
     */
    private function discovered(string $kind): array
    {
        if (isset(self::PackagePaths[$kind])) {
            $paths = $this->config->get(self::PackagePaths[$kind]);

            if (is_array($paths)) {
                return array_values(array_filter($paths, 'is_string'));
            }
        }

        $directories = [];

        foreach ($this->roots() as $root) {
            if (($root['kind'] ?? null) === $kind && is_string($root['path'] ?? null)) {
                $directories[] = $root['path'];
            }
        }

        return $directories;
    }

    /**
     * The effective registry roots (packages add theirs when RegistryConfig is first resolved).
     *
     * @return list<array<string, mixed>>
     */
    private function roots(): array
    {
        $this->container->make(RegistryConfig::class);
        $roots = $this->config->get('gcego-registry.discovery.roots', []);

        $valid = [];

        foreach (is_array($roots) ? $roots : [] as $root) {
            if (is_array($root)) {
                $valid[] = array_filter($root, static fn (mixed $key): bool => is_string($key), ARRAY_FILTER_USE_KEY);
            }
        }

        return $valid;
    }
}
