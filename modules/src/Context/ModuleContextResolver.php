<?php

declare(strict_types=1);

namespace Gcego\Modules\Context;

use Gcego\Modules\Definitions\RelationDefinition;
use Gcego\Modules\ModuleManager;
use Gcego\Modules\Operation;
use Gcego\Modules\Support\CurrentPageRoute;
use Gcego\Pages\Page;
use Gcego\Security\SecurityManager;

/**
 * Resolves the module and resource of the current request's page (scoped:
 * one per request, so nothing leaks between requests or workers).
 */
final class ModuleContextResolver
{
    private bool $resolved = false;

    private ModuleContext|ResourceContext|null $context = null;

    public function __construct(
        private readonly ModuleManager $modules,
        private readonly CurrentPageRoute $route,
        private readonly SecurityManager $security,
    ) {}

    public function module(): ?ModuleContext
    {
        $context = $this->resolve();

        return $context instanceof ResourceContext ? $context->module : $context;
    }

    public function resource(): ?ResourceContext
    {
        $context = $this->resolve();

        return $context instanceof ResourceContext ? $context : null;
    }

    private function resolve(): ModuleContext|ResourceContext|null
    {
        if ($this->resolved) {
            return $this->context;
        }

        $this->resolved = true;
        $route = $this->route->route();
        $page = $this->route->page();
        $located = $page === null ? null : $this->modules->locate($page);

        if ($route === null || $page === null || $located === null) {
            return null;
        }

        $tenant = $this->security->context()->tenant;
        $module = new ModuleContext($located['module'], $page, $tenant === null ? null : (string) $tenant->key);

        if ($located['resource'] === null) {
            return $this->context = $module;
        }

        $record = $route->parameter(Page::RecordParameter);
        $parent = $route->parameter(RelationDefinition::ParentParameter);

        return $this->context = new ResourceContext(
            $module,
            $located['resource'],
            $located['relation'],
            $located['operation'] === null ? null : Operation::tryFrom($located['operation']),
            is_string($record) || is_int($record) ? (string) $record : null,
            is_string($parent) || is_int($parent) ? (string) $parent : null,
        );
    }
}
