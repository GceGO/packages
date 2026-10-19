<?php

declare(strict_types=1);

namespace Gcego\Vault\Permissions;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Modules\Definitions\ModuleDefinition;
use Gcego\Modules\Definitions\ResourceDefinition;
use Gcego\Modules\ModuleManager;
use Gcego\Panels\PanelManager;
use Gcego\Security\Permissions\Permission as PermissionRequirement;
use Gcego\Security\Permissions\Permissions as PermissionsRequirement;
use Gcego\Vault\Models\Permission;
use Gcego\Vault\Support\Names;
use Gcego\Vault\VaultConfig;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Finds the permissions the application declares, from trusted code only:
 *
 * - Vault's own permissions (vault.*) and gcego-vault.permissions.definitions;
 * - panels.<panel>.access for every gcego/panels panel;
 * - modules.<module>.access, and <resource>.<ability> for every resource of
 *   every gcego/modules module (with the configured ability dependencies);
 * - every Permission requirement declared inside module definitions (field,
 *   column, action and page requirements: students.email.view, subjects.publish).
 *
 * Discovery describes; it never assigns. A discovered permission is held by
 * nobody until an administrator grants it.
 */
final readonly class PermissionDiscovery
{
    public function __construct(private Container $container, private VaultConfig $config) {}

    /**
     * @return array<string, array{label: string, description: string|null, group: string|null, resource: string|null, ability: string|null, scope: string, sensitive: bool, requires: list<string>, source: string}>
     */
    public function discover(): array
    {
        $found = [];

        foreach (VaultPermissions::definitions() as $name => $definition) {
            $found[$name] = $this->definition($definition['label'], 'Vault', 'vault', null, $definition['requires'], 'config', sensitive: $definition['sensitive']);
        }

        if ($this->config->discoverPanels && $this->container->bound(PanelManager::class)) {
            foreach ($this->safely(fn (): array => $this->container->make(PanelManager::class)->names()) as $panel) {
                $name = 'panels.'.$panel.'.access';

                if (Names::isPermission($name)) {
                    $found[$name] ??= $this->definition('Enter the '.$panel.' panel', 'Panels', 'panels', 'access', [], 'discovered');
                }
            }
        }

        if (($this->config->discoverModules || $this->config->discoverRequirements) && $this->container->bound(ModuleManager::class)) {
            foreach ($this->safely(fn (): array => $this->container->make(ModuleManager::class)->modules()) as $module) {
                if ($module instanceof ModuleDefinition) {
                    $found = [...$found, ...array_diff_key($this->module($module), $found)];
                }
            }
        }

        foreach ($this->config->permissions as $name => $definition) {
            $found[$name] = $this->definition($definition['label'], $definition['group'], null, null, $definition['requires'], 'config', $definition['scope'], $definition['sensitive'], $definition['description']);
        }

        ksort($found);

        return $found;
    }

    /**
     * @return array<string, array{label: string, description: string|null, group: string|null, resource: string|null, ability: string|null, scope: string, sensitive: bool, requires: list<string>, source: string}>
     */
    private function module(ModuleDefinition $module): array
    {
        $found = [];
        $group = $module->title;

        if ($this->config->discoverModules) {
            $name = 'modules.'.$module->name.'.access';

            if (Names::isPermission($name)) {
                $found[$name] = $this->definition('Open the '.$module->title.' module', 'Modules', 'modules', 'access', [], 'discovered');
            }

            foreach ($module->resources as $resource) {
                if ($resource instanceof ResourceDefinition) {
                    $found = [...$found, ...$this->resource($resource, $group)];
                }
            }
        }

        if ($this->config->discoverRequirements) {
            foreach ($this->declared($module->module) as $name) {
                $found[$name] ??= $this->definition(Names::label(substr($name, (int) strrpos($name, '.') + 1)).' ('.str_replace('.', ' › ', substr($name, 0, (int) strrpos($name, '.'))).')', $group, explode('.', $name)[0], null, [], 'discovered');
            }
        }

        return $found;
    }

    /**
     * @return array<string, array{label: string, description: string|null, group: string|null, resource: string|null, ability: string|null, scope: string, sensitive: bool, requires: list<string>, source: string}>
     */
    private function resource(ResourceDefinition $resource, string $group): array
    {
        $found = [];

        foreach ($this->config->abilities as $ability) {
            $name = $resource->name.'.'.$ability;

            if (! Names::isPermission($name)) {
                continue;
            }

            $requires = array_values(array_filter(array_map(
                static fn (string $required): string => $resource->name.'.'.$required,
                $this->config->dependencies[$ability] ?? [],
            ), fn (string $required): bool => in_array(substr($required, strlen($resource->name) + 1), $this->config->abilities, true)));

            $found[$name] = $this->definition(Names::label($ability).' '.mb_strtolower($resource->pluralLabel), $group, $resource->name, $ability, $requires, 'discovered', sensitive: $ability === 'forceDelete');
        }

        foreach ($resource->permissions as $permission) {
            if (is_string($permission) && Names::isPermission($permission)) {
                $found[$permission] ??= $this->definition(Names::label($permission), $group, $resource->name, null, [], 'discovered');
            }
        }

        return $found;
    }

    /**
     * Permission names in requirement nodes anywhere in a definition tree, nested configuration nodes included.
     *
     * @return list<string>
     */
    private function declared(ComponentInterface $component, int $depth = 0): array
    {
        if ($depth > 32) {
            return [];
        }

        $names = [];

        foreach ($component->configurations() as $node) {
            $names = [...$names, ...$this->fromNode($node)];
        }

        if (method_exists($component, 'children')) {
            foreach ($component->children() as $child) {
                if ($child instanceof ComponentInterface) {
                    $names = [...$names, ...$this->declared($child, $depth + 1)];
                }
            }
        }

        return array_values(array_unique(array_filter($names, Names::isPermission(...))));
    }

    /**
     * @return list<string>
     */
    private function fromNode(mixed $node, int $depth = 0): array
    {
        if ($depth > 4 || ! is_object($node)) {
            return [];
        }

        if ($node instanceof PermissionRequirement) {
            return $node->tenant ? [] : [$node->name];
        }

        if ($node instanceof PermissionsRequirement) {
            return $node->isTenant() ? [] : array_values($node->names);
        }

        if (! str_starts_with($node::class, 'Gcego\\')) {
            return [];
        }

        $names = [];

        foreach (get_object_vars($node) as $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                $names = [...$names, ...$this->fromNode($item, $depth + 1)];
            }
        }

        return $names;
    }

    /**
     * @param  list<string>  $requires
     * @return array{label: string, description: string|null, group: string|null, resource: string|null, ability: string|null, scope: string, sensitive: bool, requires: list<string>, source: string}
     */
    private function definition(string $label, ?string $group, ?string $resource, ?string $ability, array $requires, string $source, string $scope = Permission::ScopeAny, bool $sensitive = false, ?string $description = null): array
    {
        return ['label' => mb_substr($label, 0, 150), 'description' => $description, 'group' => $group, 'resource' => $resource, 'ability' => $ability, 'scope' => $scope, 'sensitive' => $sensitive, 'requires' => $requires, 'source' => $source];
    }

    /**
     * @param  callable(): array<mixed>  $read
     * @return array<mixed>
     */
    private function safely(callable $read): array
    {
        try {
            return $read();
        } catch (Throwable) {
            // A broken definition is reported by its own package's diagnostics; discovery skips it.
            return [];
        }
    }
}
