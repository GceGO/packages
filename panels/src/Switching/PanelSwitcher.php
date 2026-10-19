<?php

declare(strict_types=1);

namespace Gcego\Panels\Switching;

use Gcego\Panels\Access\PanelAccess;
use Gcego\Panels\Context\PanelContext;
use Gcego\Panels\PanelsConfig;
use Gcego\Panels\Registry\PanelDefinitions;
use Gcego\Panels\Routing\PanelRouteRegistrar;
use Gcego\Panels\Routing\PanelRoutes;
use Gcego\Security\SecurityConfig;

/**
 * The panels the current user may switch to from the current panel: those
 * that are not hidden, use the same guard (another guard means another
 * session identity) and that PanelAccess admits for the same user and
 * tenant. Listing a panel grants nothing: its own middleware checks again
 * when it is opened.
 */
final readonly class PanelSwitcher
{
    public function __construct(
        private PanelDefinitions $panels,
        private PanelAccess $access,
        private PanelRoutes $routes,
        private PanelRouteRegistrar $registrar,
        private PanelsConfig $config,
        private SecurityConfig $security,
    ) {}

    /**
     * @return list<PanelLink> In switcher order, the current panel included.
     */
    public function links(PanelContext $current): array
    {
        $links = [];

        foreach ($this->panels->descriptors() as $panel) {
            $isCurrent = $panel->name === $current->name();

            if (! $isCurrent && ($panel->hidden || ($panel->guard ?? $this->config->guard ?? $current->guard) !== $current->guard)) {
                continue;
            }

            if (! $isCurrent && $panel->tenant === 'required' && $current->tenant === null) {
                continue;
            }

            $context = $isCurrent ? $current : new PanelContext($panel, $current->user, $current->guard, $panel->tenant === null ? null : $current->tenant, $panel->routeNamespace($this->config->namePrefix));

            if (! $isCurrent && ! $this->access->allows($context)) {
                continue;
            }

            $parameters = str_contains($this->registrar->prefix($panel), '{'.$this->security->tenantParameter.'}') && $context->tenant !== null
                ? [$this->security->tenantParameter => (string) $context->tenant->key]
                : [];
            $url = $this->routes->url($this->routes->homeRoute($panel), $parameters);

            if ($url !== null) {
                $links[] = new PanelLink($panel->name, $panel->title, $panel->description, $url, $isCurrent);
            }
        }

        return $links;
    }
}
