<?php

declare(strict_types=1);

namespace Gcego\Modules\Compilation;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Property;
use Gcego\Data\Query\Query;
use Gcego\Data\Source;
use Gcego\Modules\Contracts\ModuleLifecycleHook;
use Gcego\Modules\Definitions\ModuleDefinition;
use Gcego\Modules\Definitions\RecordSet;
use Gcego\Modules\Definitions\RelationDefinition;
use Gcego\Modules\Definitions\ResourceDefinition;
use Gcego\Modules\Exceptions\InvalidModuleException;
use Gcego\Modules\Extensions\ModuleExtensions;
use Gcego\Modules\Lifecycle\ModuleLifecycleEvent;
use Gcego\Modules\Lifecycle\ModuleStage;
use Gcego\Modules\Module;
use Gcego\Modules\ModulesConfig;
use Gcego\Modules\Operation;
use Gcego\Modules\Pages\ResourcePageFactory;
use Gcego\Modules\Relation;
use Gcego\Modules\Relationships\ParentScope;
use Gcego\Modules\Relationships\RelationReference;
use Gcego\Modules\Resource;
use Gcego\Pages\Page;
use Gcego\Pages\PagesConfig;
use Gcego\Security\Contracts\Requirement;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\Container as ContainerContract;
use WeakMap;

/**
 * Compiles a Module into a ModuleDefinition, in stages (ModuleStage):
 * validate, apply extensions, normalize resources and relations, derive
 * pages. Pure: no queries, routes or rendering. Results are memoized per
 * definition object, so registry loads and runtime lookups share them.
 */
final class ModuleCompiler
{
    /**
     * @var WeakMap<Module, ModuleDefinition>
     */
    private WeakMap $definitions;

    /**
     * @var WeakMap<Module, list<Page>>
     */
    private WeakMap $pages;

    public function __construct(
        private readonly ModulesConfig $config,
        private readonly ModuleExtensions $extensions,
        private readonly ResourcePageFactory $factory,
        private readonly ContainerContract $container,
    ) {
        $this->definitions = new WeakMap;
        $this->pages = new WeakMap;
    }

    /**
     * The application's compiler, or a default one outside Laravel (registry tooling, plain PHP).
     */
    public static function instance(): self
    {
        $container = Container::getInstance();

        if ($container->bound(self::class)) {
            $compiler = $container->make(self::class);

            if ($compiler instanceof self) {
                return $compiler;
            }
        }

        $config = new ModulesConfig;

        return new self($config, new ModuleExtensions($config, $container), new ResourcePageFactory($config), $container);
    }

    /**
     * @throws InvalidModuleException
     */
    public function compile(Module $module): ModuleDefinition
    {
        if (isset($this->definitions[$module])) {
            return $this->definitions[$module];
        }

        self::assertValid(self::withoutPages($module), 'module "'.$module->moduleName().'"');
        $this->tell(new ModuleLifecycleEvent(ModuleStage::Validated, $module->moduleName()));

        $extended = $this->extensions->apply($module);

        if ($extended !== $module) {
            self::assertValid(self::withoutPages($extended), 'module "'.$module->moduleName().'" (extended)');
        }

        $this->tell(new ModuleLifecycleEvent(ModuleStage::Extended, $module->moduleName()));
        $definition = $this->normalize($extended);
        $this->tell(new ModuleLifecycleEvent(ModuleStage::Normalized, $definition->name, $definition));

        return $this->definitions[$module] = $definition;
    }

    /**
     * The pages a module derives (memoized per definition).
     *
     * @return list<Page>
     *
     * @throws InvalidModuleException
     */
    public function pages(Module $module): array
    {
        if (isset($this->pages[$module])) {
            return $this->pages[$module];
        }

        $definition = $this->compile($module);
        $pages = $this->factory->pages($definition);
        $this->tell(new ModuleLifecycleEvent(ModuleStage::PagesDerived, $definition->name, $definition, array_map(static fn (Page $page): string => $page->pageName(), $pages)));

        return $this->pages[$module] = $pages;
    }

    private function normalize(Module $module): ModuleDefinition
    {
        $name = $module->moduleName();
        $requirements = $module->requirements();
        $resources = [];

        foreach ($module->resources() as $resource) {
            $resources[] = $this->resource($module, $resource, $requirements);
        }

        $pages = [];

        foreach ($module->pages() as $page) {
            $pages[] = $this->placePage($page, $name.'.'.$page->pageName(), $module->slug() === null ? null : $module->slug().'/'.str_replace('.', '/', $page->pageName()), $requirements, null);
        }

        return new ModuleDefinition($module, $name, $module->title(), $module->description(), $module->icon(), $module->slug(), $module->version(), $module->navigation(), $module->navigationGroup(), $module->navigationOrder(), $requirements, $resources, $pages);
    }

    /**
     * @param  list<Requirement>  $moduleRequirements
     */
    private function resource(Module $module, Resource $resource, array $moduleRequirements): ResourceDefinition
    {
        $moduleName = $module->moduleName();
        $name = $resource->resourceName();
        $base = $name === $moduleName ? $moduleName : $moduleName.'.'.$name;
        $path = ($module->slug() === null ? '' : $module->slug().'/').$resource->slug();
        $requirements = [...$moduleRequirements, ...$resource->requirements()];
        $source = $resource->source() ?? throw InvalidModuleException::invalid('resource "'.$name.'"', 'it needs a Source or a Model.');
        $operations = $this->config->autoPages ? $resource->operations($this->config->defaultOperations) : [];
        $custom = [];
        $overridden = [];

        foreach ($resource->pages() as $page) {
            $operation = Operation::tryFrom($page->pageName());

            if ($operation !== null && $this->config->autoPages) {
                $overridden[] = $operation;
                $definitionName = $operation === Operation::Index ? $base : $base.'.'.$operation->value;
                $custom[] = $this->placePage(
                    $page,
                    $definitionName,
                    self::operationPath($path, $operation),
                    $requirements,
                    $source,
                    $base.'.'.$operation->value,
                );

                continue;
            }

            $custom[] = $this->placePage($page, $base.'.'.$page->pageName(), $page->requiresRecord() ? $path.'/{record}/'.self::segments($page->pageName()) : $path.'/'.self::segments($page->pageName()), $requirements, $source);
        }

        $definition = new ResourceDefinition(
            $moduleName,
            $name,
            $base,
            $path,
            $resource->label(),
            $resource->pluralLabel(),
            $resource->modelClass(),
            $source,
            $resource->table(),
            $resource->form(),
            $resource->infolist(),
            $resource->action('create'),
            $resource->action('edit'),
            $resource->recordActions(),
            array_values(array_filter($operations, static fn (Operation $operation): bool => ! in_array($operation, $overridden, true))),
            $requirements,
            $resource->permissions(),
            $resource->recordKey(),
            $resource->recordTitle(),
            $resource->icon(),
            $resource->inNavigation(),
            $resource->navigationLabel(),
            $resource->navigationOrder(),
            [],
            $custom,
            $resource->hasNavigationBadge(),
            $resource->navigationBadgeVariant(),
        );

        if (! $this->config->relationships || ! $this->config->autoPages) {
            return $definition;
        }

        $relations = [];

        foreach ($resource->relations() as $relation) {
            $relations[] = $this->relation($module, $definition, $relation);
        }

        return new ResourceDefinition(
            $definition->module, $definition->name, $definition->pageBase, $definition->path, $definition->label, $definition->pluralLabel, $definition->model, $definition->source,
            $definition->table, $definition->form, $definition->infolist, $definition->createAction, $definition->editAction, $definition->actions, $definition->operations,
            $definition->requirements, $definition->permissions, $definition->recordKey, $definition->recordTitle, $definition->icon,
            $definition->navigation, $definition->navigationLabel, $definition->navigationOrder, $relations, $definition->pages, $definition->navigationBadge, $definition->navigationBadgeVariant,
        );
    }

    private function relation(Module $module, ResourceDefinition $parent, Relation $relation): RelationDefinition
    {
        $target = $relation->targetResource() === null ? null : $module->resource($relation->targetResource());
        $model = $relation->model() ?? $target?->modelClass();
        $baseSource = $target?->source() ?? ($model === null ? null : new Source($model));

        if ($baseSource === null) {
            throw InvalidModuleException::invalid(sprintf('relation "%s" of "%s"', $relation->relationship(), $parent->name), 'it needs a Model or a Resource with a Source.');
        }

        $segment = $relation->recordSetName();
        $base = $parent->pageBase.'.'.$segment;
        $path = $parent->path.'/{'.RelationDefinition::ParentParameter.'}/'.$relation->slug();
        $own = $relation->actions();
        $inherit = $own === [] && $target !== null;
        $actionsOf = static fn (RecordSet $set): array => $set->recordActions();
        $table = $relation->table() ?? $target?->table();
        $form = $relation->form() ?? $target?->form();
        $infolist = $relation->infolist() ?? $target?->infolist();
        $create = $relation->action('create') ?? ($inherit ? $target->action('create') : null);
        $edit = $relation->action('edit') ?? ($inherit ? $target->action('edit') : null);
        $operations = self::operations($relation->listedOperations(), $table !== null, $form !== null, $infolist !== null, $create !== null, $edit !== null, $this->config->defaultOperations);
        $pages = array_map(static fn (Operation $operation): string => str_replace('.', '-', $operation === Operation::Index ? $base : $base.'.'.$operation->value), $operations);
        $reference = new RelationReference($parent->module, $parent->name, $relation->relationship(), $parent->source, $model, $pages);

        return new RelationDefinition(
            $parent->module,
            $segment,
            $base,
            $path,
            $relation->hasExplicit(Relation::Label) || $target === null ? $relation->label() : $target->label(),
            $relation->hasExplicit(Relation::PluralLabel) || $target === null ? $relation->pluralLabel() : $target->pluralLabel(),
            $model,
            $baseSource->withConfigurations(...[...$baseSource->configurations()->all(), new Query(new ParentScope($reference))]),
            $table,
            $form,
            $infolist,
            $create,
            $edit,
            $inherit ? $actionsOf($target) : $relation->recordActions(),
            $operations,
            [...$parent->requirements, ...$relation->requirements()],
            $relation->permissions(),
            $relation->recordKey() ?? $target?->recordKey(),
            $relation->recordTitle() ?? $target?->recordTitle(),
            $relation->icon() ?? $target?->icon(),
            $parent->name,
            $relation->relationship(),
            $parent->source,
            $parent->recordKey ?? $this->defaultRecordKey(),
            $parent->pageBase,
            $reference,
        );
    }

    /**
     * Name, place and secure a custom page: its name and (unless explicit)
     * path and route follow the module, it gets the module's (and resource's)
     * requirements, and the resource Source when it has none.
     *
     * @param  list<Requirement>  $requirements
     */
    private function placePage(Page $page, string $name, ?string $path, array $requirements, ?Source $source, ?string $route = null): Page
    {
        $properties = [new Property(Page::Name, $name)];

        if ($path !== null && ! $page->hasExplicit(Page::Path)) {
            $properties[] = new Property(Page::Path, $path);
        }

        if ($route !== null && ! $page->hasExplicit(Page::Route)) {
            $properties[] = new Property(Page::Route, $route);
        }

        $placed = $page->withProperty(...$properties);

        if ($requirements !== []) {
            $placed = $placed->withConfigurations(...$placed->configurations()->all(), ...$requirements);
        }

        if ($source !== null && $placed->source() === null) {
            $placed = $placed->withAppendedChildren($source);
        }

        // Pages are validated once placed: their Source and name come from the module.
        self::assertValid($placed, 'page "'.$name.'"');

        return $placed;
    }

    /**
     * The module without its resources' custom pages, which are validated after placement
     * (they may rely on the resource's Source).
     */
    private static function withoutPages(Module $module): Module
    {
        $children = [];

        foreach ($module->children() as $child) {
            $children[] = $child instanceof Resource ? $child->withChildren(...array_values(array_filter($child->children()->all(), static fn (ComponentInterface $node): bool => ! $node instanceof Page))) : $child;
        }

        return $module->withChildren(...$children);
    }

    /**
     * @param  list<Operation>|null  $listed
     * @param  list<Operation>  $defaults
     * @return list<Operation>
     */
    public static function operations(?array $listed, bool $table, bool $form, bool $infolist, bool $create, bool $edit, array $defaults = []): array
    {
        $operations = [];

        foreach (Operation::cases() as $operation) {
            $declared = match ($operation) {
                Operation::Index => $table,
                Operation::View => $infolist,
                Operation::Create => $form && ($create || in_array($operation, $listed ?? [], true) || in_array($operation, $defaults, true)),
                Operation::Edit => $form && ($edit || in_array($operation, $listed ?? [], true) || in_array($operation, $defaults, true)),
            };

            if ($declared && ($listed === null || in_array($operation, $listed, true))) {
                $operations[] = $operation;
            }
        }

        return $operations;
    }

    public static function operationPath(string $path, Operation $operation): string
    {
        return match ($operation) {
            Operation::Index => $path,
            Operation::Create => $path.'/create',
            Operation::View => $path.'/{record}',
            Operation::Edit => $path.'/{record}/edit',
        };
    }

    private function defaultRecordKey(): string
    {
        return $this->container->bound(PagesConfig::class) ? $this->container->make(PagesConfig::class)->recordKey : 'key';
    }

    /**
     * "syllabus.overview" -> "syllabus/overview"; "_" becomes "-".
     */
    private static function segments(string $name): string
    {
        return str_replace(['.', '_'], ['/', '-'], $name);
    }

    /**
     * @throws InvalidModuleException
     */
    private static function assertValid(ComponentInterface $component, string $subject): void
    {
        $result = $component->validate();

        if (! $result->isValid()) {
            throw InvalidModuleException::problems($subject, array_map(static fn (object $error): string => method_exists($error, 'format') ? (string) $error->format() : '', $result->errors()));
        }
    }

    private function tell(ModuleLifecycleEvent $event): void
    {
        foreach ($this->config->hooks as $hook) {
            $instance = $this->container->make($hook);

            if ($instance instanceof ModuleLifecycleHook) {
                $instance->handle($event);
            }
        }
    }
}
