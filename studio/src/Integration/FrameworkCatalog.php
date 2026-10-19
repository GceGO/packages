<?php

declare(strict_types=1);

namespace Gcego\Studio\Integration;

use Gcego\Modules\ModuleManager;
use Gcego\Navigation\NavigationManager;
use Gcego\Pages\PageManager;
use Gcego\Panels\PanelManager;
use Gcego\Registry\Contracts\RegistryInterface;
use Throwable;

/**
 * Panels, modules, pages and navigation as their own packages report them
 * (PanelManager, ModuleManager, PageManager and NavigationManager
 * inspectors). Studio adds no second manager: it reads, aggregates and
 * labels. A definition that fails to load is reported, not hidden.
 */
final readonly class FrameworkCatalog
{
    public function __construct(
        private PanelManager $panels,
        private ModuleManager $modules,
        private PageManager $pages,
        private NavigationManager $navigation,
        private RegistryInterface $registry,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function panels(): array
    {
        $panels = [];

        foreach ($this->panels->names() as $name) {
            try {
                $panels[] = $this->panels->inspect($name)->toArray();
            } catch (Throwable $exception) {
                $panels[] = ['identity' => 'panel:'.$name, 'healthy' => false, 'diagnostics' => [['level' => 'error', 'code' => 'panel.unloadable', 'message' => $exception->getMessage()]]];
            }
        }

        return $panels;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function modules(): array
    {
        $modules = [];

        foreach ($this->modules->names() as $name) {
            try {
                $modules[] = $this->modules->inspect($name)->toArray();
            } catch (Throwable $exception) {
                $modules[] = ['module' => $name, 'healthy' => false, 'diagnostics' => [['level' => 'error', 'code' => 'module.unloadable', 'message' => $exception->getMessage()]]];
            }
        }

        return $modules;
    }

    /**
     * Pages registered directly (module pages are listed under their modules).
     *
     * @return list<array<string, mixed>>
     */
    public function pages(): array
    {
        $pages = [];

        foreach ($this->registry->descriptors('page') as $descriptor) {
            try {
                $pages[] = $this->pages->inspect($descriptor->name())->toArray();
            } catch (Throwable $exception) {
                $pages[] = ['identity' => 'page:'.$descriptor->name(), 'healthy' => false, 'diagnostics' => [['level' => 'error', 'code' => 'page.unloadable', 'message' => $exception->getMessage()]]];
            }
        }

        return $pages;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function navigation(): array
    {
        $navigations = [];

        foreach ($this->registry->descriptors('navigation') as $descriptor) {
            try {
                $navigations[] = $this->navigation->inspect($descriptor->name())->toArray();
            } catch (Throwable $exception) {
                $navigations[] = ['identity' => 'navigation:'.$descriptor->name(), 'healthy' => false, 'diagnostics' => [['level' => 'error', 'code' => 'navigation.unloadable', 'message' => $exception->getMessage()]]];
            }
        }

        return $navigations;
    }

    /**
     * Definition counts per registry kind.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];

        foreach ($this->registry->descriptors() as $descriptor) {
            $counts[$descriptor->kind()] = ($counts[$descriptor->kind()] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }
}
