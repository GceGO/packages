<?php

declare(strict_types=1);

namespace Gcego\Panels\Registry;

use Gcego\Modules\Registry\ModuleKind;
use Gcego\Pages\Registry\PageDefinitions;
use Gcego\Panels\Exceptions\InvalidPanelException;
use Gcego\Registry\Contracts\RegistryInterface;
use Throwable;

/**
 * Which pages belong to a panel: the pages of its modules (from the module's
 * indexed page map) and the pages it references, read from the registry
 * index only. Unknown references are configuration errors.
 */
final readonly class PanelMembership
{
    public function __construct(private RegistryInterface $registry, private PageDefinitions $pages) {}

    /**
     * Page identity => the module it comes from (null for referenced pages).
     *
     * @return array<string, string|null>
     *
     * @throws InvalidPanelException
     */
    public function pages(PanelDescriptor $panel): array
    {
        $pages = [];

        foreach ($panel->modules as $module) {
            foreach ($this->modulePages($panel, $module) as $identity) {
                $pages[$identity] = $module;
            }
        }

        foreach ($panel->pages as $page) {
            $pages[$this->pageIdentity($panel, $page)] ??= null;
        }

        return $pages;
    }

    /**
     * The identity of a referenced page ("overview" or "page:reports.monthly").
     *
     * @throws InvalidPanelException
     */
    public function pageIdentity(PanelDescriptor $panel, string $page): string
    {
        foreach ([$page, 'page:'.$page] as $candidate) {
            try {
                $identity = $this->pages->identity($candidate);
            } catch (Throwable) {
                continue;
            }

            if ($this->registry->has($identity)) {
                return $identity;
            }
        }

        throw InvalidPanelException::invalid('Panel "'.$panel->name.'"', sprintf('it references the page "%s", which is not registered.', mb_substr($page, 0, 100)));
    }

    /**
     * @return list<string>
     *
     * @throws InvalidPanelException
     */
    public function modulePages(PanelDescriptor $panel, string $module): array
    {
        $identity = ModuleKind::Id.':'.$module;

        if (! $this->registry->has($identity)) {
            throw InvalidPanelException::invalid('Panel "'.$panel->name.'"', sprintf('it references the module "%s", which is not registered.', $module));
        }

        $descriptor = $this->registry->descriptor($identity);
        $map = json_decode(is_string($descriptor->metadata['pages'] ?? null) ? $descriptor->metadata['pages'] : '{}', true);
        $scope = $descriptor->scope();
        $pages = [];

        foreach (is_array($map) ? array_keys($map) : [] as $pageName) {
            $page = 'page:'.($scope === null ? '' : $scope.'.').str_replace('.', '-', (string) $pageName);

            if ($this->registry->has($page)) {
                $pages[] = $page;
            }
        }

        return $pages;
    }
}
