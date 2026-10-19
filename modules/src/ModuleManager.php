<?php

declare(strict_types=1);

namespace Gcego\Modules;

use Gcego\Modules\Compilation\ModuleCompiler;
use Gcego\Modules\Context\ModuleContext;
use Gcego\Modules\Context\ModuleContextResolver;
use Gcego\Modules\Context\ResourceContext;
use Gcego\Modules\Contracts\ModuleExtension;
use Gcego\Modules\Definitions\ModuleDefinition;
use Gcego\Modules\Definitions\RelationDefinition;
use Gcego\Modules\Definitions\ResourceDefinition;
use Gcego\Modules\Diagnostics\ModuleInspector;
use Gcego\Modules\Diagnostics\ModuleReport;
use Gcego\Modules\Exceptions\ModuleNotFoundException;
use Gcego\Modules\Extensions\ModuleExtensions;
use Gcego\Modules\Registry\ModuleKind;
use Gcego\Pages\PageManager;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Registry\Index\DefinitionDescriptor;
use Illuminate\Contracts\Container\Container;

/**
 * The entry point of gcego/modules (no facade):
 *
 *     $modules = app(ModuleManager::class);
 *
 *     $modules->names();                                          // ["academics", "students"]
 *     $modules->module('academics')->resources;                   // compiled ResourceDefinitions
 *     $modules->resource('academics', 'subjects')->operations;    // [Operation::Index, …]
 *     $modules->page('academics', 'subjects', Operation::Edit);   // "page:academics-subjects-edit"
 *     $modules->url('academics', 'subjects', Operation::Edit, 7); // "/admin/subjects/7/edit"
 *     $modules->current();                                        // the current request's ResourceContext
 *     $modules->inspect('academics');                             // diagnostics
 *
 * Modules come from gcego/registry only (discovered, provided or registered
 * with register()); names from requests are never trusted (404). Every
 * service is resolved per call, so the manager is safe in long-running workers.
 */
final readonly class ModuleManager
{
    public function __construct(private Container $container) {}

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(static fn (DefinitionDescriptor $descriptor): string => $descriptor->name(), $this->descriptors());
    }

    public function has(string $name): bool
    {
        return $this->descriptor($name) !== null;
    }

    /**
     * The compiled module.
     *
     * @throws ModuleNotFoundException
     */
    public function module(string $name): ModuleDefinition
    {
        return $this->compiler()->compile($this->definition($name));
    }

    /**
     * The module definition as declared (before extensions).
     *
     * @throws ModuleNotFoundException
     */
    public function definition(string $name): Module
    {
        $descriptor = $this->descriptor($name) ?? throw ModuleNotFoundException::named('module', $name);
        $module = $this->registry()->get($descriptor->key());

        return $module instanceof Module ? $module : throw ModuleNotFoundException::named('module', $name);
    }

    /**
     * Every compiled module, in registry order.
     *
     * @return list<ModuleDefinition>
     */
    public function modules(): array
    {
        return array_map(fn (string $name): ModuleDefinition => $this->module($name), $this->names());
    }

    /**
     * @throws ModuleNotFoundException
     */
    public function resource(string $module, string $resource): ResourceDefinition
    {
        return $this->module($module)->resource($resource) ?? throw ModuleNotFoundException::named('resource', $module.'.'.$resource);
    }

    /**
     * @throws ModuleNotFoundException
     */
    public function relation(string $module, string $resource, string $relation): RelationDefinition
    {
        return $this->resource($module, $resource)->relation($relation) ?? throw ModuleNotFoundException::named('relation', $module.'.'.$resource.'.'.$relation);
    }

    /**
     * The page identity of a resource (or relation) operation, or null when the resource has no such page.
     *
     * @throws ModuleNotFoundException
     */
    public function page(string $module, string $resource, Operation $operation, ?string $relation = null): ?string
    {
        $set = $relation === null ? $this->resource($module, $resource) : $this->relation($module, $resource, $relation);
        $exposed = $set instanceof ResourceDefinition ? $set->exposes($operation) : $set->has($operation);

        return $exposed ? $this->pageIdentity($module, $set->pageName($operation)) : null;
    }

    /**
     * The URL of a resource operation's page, or null when it has none or no route.
     *
     * @param  array<string, string|int>  $parameters  extra route parameters ("parent" for relation pages)
     *
     * @throws ModuleNotFoundException
     */
    public function url(string $module, string $resource, Operation $operation, string|int|null $record = null, array $parameters = [], ?string $relation = null): ?string
    {
        $page = $this->page($module, $resource, $operation, $relation);

        return $page === null ? null : $this->container->make(PageManager::class)->url($page, $record, $parameters);
    }

    /**
     * The module, resource, relation and operation a page belongs to (from the index, without loading pages).
     *
     * @return array{module: ModuleDefinition, resource: ?ResourceDefinition, relation: ?RelationDefinition, operation: ?string}|null
     */
    public function locate(string $page): ?array
    {
        $name = str_starts_with($page, 'page:') ? substr($page, 5) : $page;
        $dot = strrpos($name, '.');
        $scope = $dot === false ? null : substr($name, 0, $dot);
        $registryName = $dot === false ? $name : substr($name, $dot + 1);

        foreach ($this->descriptors() as $descriptor) {
            if ($descriptor->scope() !== $scope) {
                continue;
            }

            $map = json_decode(is_string($descriptor->metadata['pages'] ?? null) ? $descriptor->metadata['pages'] : '[]', true);

            foreach (is_array($map) ? $map : [] as $pageName => $entry) {
                if (str_replace('.', '-', (string) $pageName) !== $registryName || ! is_array($entry)) {
                    continue;
                }

                $module = $this->compiler()->compile($this->definition($descriptor->name()));
                $resource = is_string($entry['resource'] ?? null) ? $module->resource($entry['resource']) : null;
                $relation = $resource !== null && is_string($entry['relation'] ?? null) ? $resource->relation($entry['relation']) : null;

                return ['module' => $module, 'resource' => $resource, 'relation' => $relation, 'operation' => is_string($entry['operation'] ?? null) ? $entry['operation'] : null];
            }
        }

        return null;
    }

    /**
     * The module of the current request's page, if it belongs to one.
     */
    public function currentModule(): ?ModuleContext
    {
        return $this->container->make(ModuleContextResolver::class)->module();
    }

    /**
     * The resource of the current request's page, if it belongs to one.
     */
    public function current(): ?ResourceContext
    {
        return $this->container->make(ModuleContextResolver::class)->resource();
    }

    /**
     * Register a module at runtime (its pages are registered with it).
     * Page routes are registered from the registry when the application
     * boots, so register modules in a service provider's register() or boot().
     */
    public function register(Module $module, ?string $scope = null): DefinitionDescriptor
    {
        return $this->registry()->register($module, $scope);
    }

    /**
     * Add a trusted extension (after the configured ones).
     *
     * @param  class-string<ModuleExtension>  $extension
     */
    public function extend(string $extension): void
    {
        $this->container->make(ModuleExtensions::class)->register($extension);
    }

    public function inspect(string $module): ModuleReport
    {
        return $this->container->make(ModuleInspector::class)->inspect($module);
    }

    /**
     * @return list<ModuleReport>
     */
    public function inspectAll(): array
    {
        return array_map(fn (string $name): ModuleReport => $this->inspect($name), $this->names());
    }

    /**
     * "page:academics-subjects" (with the module's registry scope, if any).
     */
    public function pageIdentity(string $module, string $pageName): string
    {
        $scope = $this->descriptor($module)?->scope();

        return 'page:'.($scope === null ? '' : $scope.'.').str_replace('.', '-', $pageName);
    }

    private function compiler(): ModuleCompiler
    {
        return $this->container->make(ModuleCompiler::class);
    }

    private function registry(): RegistryInterface
    {
        return $this->container->make(RegistryInterface::class);
    }

    /**
     * @return list<DefinitionDescriptor>
     */
    private function descriptors(): array
    {
        return $this->registry()->descriptors(ModuleKind::Id);
    }

    private function descriptor(string $name): ?DefinitionDescriptor
    {
        foreach ($this->descriptors() as $descriptor) {
            if ($descriptor->name() === $name || $descriptor->key() === $name) {
                return $descriptor;
            }
        }

        return null;
    }
}
