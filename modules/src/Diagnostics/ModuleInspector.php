<?php

declare(strict_types=1);

namespace Gcego\Modules\Diagnostics;

use Gcego\Modules\Definitions\ModuleDefinition;
use Gcego\Modules\Definitions\RelationDefinition;
use Gcego\Modules\Definitions\ResourceDefinition;
use Gcego\Modules\ModuleManager;
use Gcego\Modules\ModulesConfig;
use Gcego\Modules\Operation;
use Gcego\Modules\Relationships\Attach;
use Gcego\Modules\Relationships\Detach;
use Gcego\Pages\Diagnostics\PageDiagnostic;
use Gcego\Pages\PageManager;
use Gcego\Registry\Contracts\RegistryInterface;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Throwable;

/**
 * Read-only diagnostics of a module: definition errors, resources that
 * expose nothing, unused forms, models without policies, relationships
 * that do not exist, pages missing from the registry (stale cache), the
 * page inspector's findings for every derived page (route conflicts,
 * missing renderers…), and size warnings. It never queries or opens a page.
 */
final readonly class ModuleInspector
{
    private const int MaxResources = 30;

    private const int MaxRelations = 10;

    public function __construct(private Container $container, private ModulesConfig $config) {}

    public function inspect(string $name): ModuleReport
    {
        $modules = $this->container->make(ModuleManager::class);

        try {
            $module = $modules->module($name);
        } catch (Throwable $exception) {
            return new ModuleReport($name, [], [], [new ModuleDiagnostic('module.invalid', ModuleDiagnostic::Error, $exception->getMessage(), 'module:'.$name)]);
        }

        $diagnostics = [];
        $resources = [];
        $pages = [];

        foreach ($module->resources as $resource) {
            $resourcePages = [];

            foreach ($resource->operations as $operation) {
                $resourcePages[] = $modules->pageIdentity($module->name, $resource->pageName($operation));
            }

            foreach ($resource->pages as $page) {
                $resourcePages[] = $modules->pageIdentity($module->name, $page->pageName());
            }

            foreach ($resource->relations as $relation) {
                foreach ($relation->operations as $operation) {
                    $resourcePages[] = $modules->pageIdentity($module->name, $relation->pageName($operation));
                }

                $diagnostics = [...$diagnostics, ...$this->relation($resource, $relation)];
            }

            $resources[$resource->name] = [
                'operations' => array_map(static fn (Operation $operation): string => $operation->value, $resource->operations),
                'pages' => $resourcePages,
                'relations' => array_map(static fn (RelationDefinition $relation): string => $relation->name, $resource->relations),
                'model' => $resource->model,
            ];
            $pages = [...$pages, ...$resourcePages];
            $diagnostics = [...$diagnostics, ...$this->resource($resource)];
        }

        foreach ($module->pages as $page) {
            $pages[] = $modules->pageIdentity($module->name, $page->pageName());
        }

        $diagnostics = [...$diagnostics, ...$this->pages($pages), ...$this->module($module)];

        return new ModuleReport($module->name, $resources, $pages, $diagnostics);
    }

    /**
     * @return list<ModuleDiagnostic>
     */
    private function resource(ResourceDefinition $resource): array
    {
        $subject = $resource->identity();
        $diagnostics = [];

        if ($resource->operations === [] && $resource->pages === []) {
            $diagnostics[] = new ModuleDiagnostic('resource.empty', ModuleDiagnostic::Warning, 'The resource has no pages: declare a Table (list), a Form with Create or Edit, or an Infolist (view). Nothing is exposed on its own.', $subject);
        }

        if ($resource->form !== null && ! $resource->has(Operation::Create) && ! $resource->has(Operation::Edit)) {
            $diagnostics[] = new ModuleDiagnostic('resource.form', ModuleDiagnostic::Warning, 'The Form is not used: add the Create or Edit action (or list the operation in Operations).', $subject);
        }

        $diagnostics = [...$diagnostics, ...$this->authorization($resource, $subject)];

        if (count($resource->relations) > self::MaxRelations) {
            $diagnostics[] = new ModuleDiagnostic('performance.relations', ModuleDiagnostic::Warning, sprintf('%d relations: each adds up to four pages and a link on the view page.', count($resource->relations)), $subject);
        }

        return $diagnostics;
    }

    /**
     * @return list<ModuleDiagnostic>
     */
    private function relation(ResourceDefinition $parent, RelationDefinition $relation): array
    {
        $subject = $relation->identity();
        $diagnostics = $this->authorization($relation, $subject);
        $parentModel = $parent->model;

        if ($parentModel === null || ! is_subclass_of($parentModel, Model::class)) {
            return [...$diagnostics, new ModuleDiagnostic('relation.parent', ModuleDiagnostic::Error, 'Relations need a parent resource with an Eloquent model.', $subject)];
        }

        if (! method_exists($parentModel, $relation->relationship)) {
            return [...$diagnostics, new ModuleDiagnostic('relation.missing', ModuleDiagnostic::Error, sprintf('%s has no "%s" relationship method.', $parentModel, $relation->relationship), $subject)];
        }

        try {
            $instance = (new $parentModel)->{$relation->relationship}();
        } catch (Throwable $exception) {
            return [...$diagnostics, new ModuleDiagnostic('relation.invalid', ModuleDiagnostic::Error, sprintf('%s::%s() failed: %s', $parentModel, $relation->relationship, class_basename($exception)), $subject)];
        }

        if (! $instance instanceof Relation) {
            return [...$diagnostics, new ModuleDiagnostic('relation.invalid', ModuleDiagnostic::Error, sprintf('%s::%s() does not return an Eloquent relationship.', $parentModel, $relation->relationship), $subject)];
        }

        if ($relation->model !== null && ! $instance->getRelated() instanceof $relation->model) {
            $diagnostics[] = new ModuleDiagnostic('relation.model', ModuleDiagnostic::Error, sprintf('The relationship relates %s, but the relation shows %s.', $instance->getRelated()::class, $relation->model), $subject);
        }

        foreach ($relation->actions as $action) {
            if (($action instanceof Attach || $action instanceof Detach) && ! $instance instanceof BelongsToMany) {
                $diagnostics[] = new ModuleDiagnostic('relation.attach', ModuleDiagnostic::Error, sprintf('%s needs a BelongsToMany relationship; "%s" is a %s.', class_basename($action), $relation->relationship, class_basename($instance)), $subject);
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<ModuleDiagnostic>
     */
    private function authorization(ResourceDefinition|RelationDefinition $set, string $subject): array
    {
        if ($this->config->authorizeByDefault && $set->model !== null && $this->container->make(Gate::class)->getPolicyFor($set->model) === null) {
            return [new ModuleDiagnostic('authorization.policy', ModuleDiagnostic::Warning, sprintf('%s has no policy: its pages are authorized by the model policy, so everyone is denied until one exists.', $set->model), $subject)];
        }

        if (! $this->config->authorizeByDefault && $set->requirements === [] && $set->permissions === []) {
            return [new ModuleDiagnostic('authorization.requirements', ModuleDiagnostic::Warning, 'No requirements or permissions: the pages follow gcego-pages.authorization.default.', $subject)];
        }

        return [];
    }

    /**
     * @param  list<string>  $pages
     * @return list<ModuleDiagnostic>
     */
    private function pages(array $pages): array
    {
        $registry = $this->container->make(RegistryInterface::class);
        $inspector = $this->container->make(PageManager::class);
        $diagnostics = [];

        foreach ($pages as $page) {
            if (! $registry->has($page)) {
                $diagnostics[] = new ModuleDiagnostic('page.missing', ModuleDiagnostic::Error, 'The page is not in the registry: rebuild its cache (php artisan gcego:registry-cache).', $page);

                continue;
            }

            foreach ($inspector->inspect($page)->diagnostics as $finding) {
                /** @var PageDiagnostic $finding */
                $diagnostics[] = new ModuleDiagnostic('page.'.$finding->check, $finding->level === PageDiagnostic::Error ? ModuleDiagnostic::Error : ModuleDiagnostic::Warning, $finding->message, $page);
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<ModuleDiagnostic>
     */
    private function module(ModuleDefinition $module): array
    {
        $diagnostics = [];

        if (count($module->resources) > self::MaxResources) {
            $diagnostics[] = new ModuleDiagnostic('performance.resources', ModuleDiagnostic::Warning, sprintf('%d resources in one module: split it so navigation groups stay usable.', count($module->resources)), $module->identity());
        }

        $navigation = $module->navigation ?? $this->config->autoNavigation;
        $listed = array_filter($module->resources, static fn (ResourceDefinition $resource): bool => $resource->navigation && $resource->exposes(Operation::Index));

        if ($navigation && $listed === [] && $module->pages === []) {
            $diagnostics[] = new ModuleDiagnostic('navigation.empty', ModuleDiagnostic::Warning, 'The module is in the navigation but has no list or page to link to.', $module->identity());
        }

        return $diagnostics;
    }
}
