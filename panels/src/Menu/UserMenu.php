<?php

declare(strict_types=1);

namespace Gcego\Panels\Menu;

use Gcego\Panels\Context\PanelContext;
use Gcego\Panels\PanelsConfig;
use Gcego\Panels\Registry\PanelDefinitions;
use Gcego\Panels\Routing\PanelRoutes;
use Gcego\Security\SecurityManager;
use Throwable;

/**
 * The user menu of a panel: the panel's UserMenuItems, then
 * gcego-panels.user_menu, then "Sign out" when a "logout" route exists
 * (Laravel's or gcego/sentinel's, posted with the CSRF token). Items whose route
 * does not exist, or whose requirements deny, are left out.
 */
final readonly class UserMenu
{
    public const string LogoutRoute = 'logout';

    public function __construct(
        private PanelDefinitions $panels,
        private PanelRoutes $routes,
        private PanelsConfig $config,
        private SecurityManager $security,
    ) {}

    /**
     * @return list<UserMenuLink>
     */
    public function links(PanelContext $context): array
    {
        $links = [];

        foreach ($this->panels->resolve($context->name())->userMenu() as $item) {
            try {
                $allowed = $item->requirements() === [] || ! $this->security->check(...$item->requirements())->denied();
            } catch (Throwable) {
                $allowed = false;
            }

            $url = $allowed ? $this->routes->url($item->route()) : null;

            if ($url !== null) {
                $links[] = new UserMenuLink($item->label(), $url, $item->icon());
            }
        }

        foreach ($this->config->userMenu as $label => $route) {
            $url = $this->routes->url($route);

            if ($url !== null) {
                $links[] = new UserMenuLink(__($label), $url);
            }
        }

        $logout = $this->routes->url(self::LogoutRoute);

        if ($logout !== null && in_array('POST', $this->routes->route(self::LogoutRoute)?->methods() ?? [], true)) {
            $links[] = new UserMenuLink(__('gcego-panels::messages.sign_out'), $logout, 'arrow-right-start-on-rectangle', post: true);
        }

        return $links;
    }
}
