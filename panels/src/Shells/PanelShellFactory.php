<?php

declare(strict_types=1);

namespace Gcego\Panels\Shells;

use Gcego\Chroma\ChromaManager;
use Gcego\Panels\Assets\PanelAssetResolver;
use Gcego\Panels\Context\PanelContext;
use Gcego\Panels\Context\PanelContextResolver;
use Gcego\Panels\Menu\UserMenu;
use Gcego\Panels\PanelsConfig;
use Gcego\Panels\Registry\PanelDefinitions;
use Gcego\Panels\Routing\PanelRoutes;
use Gcego\Panels\Support\SafeUrl;
use Gcego\Panels\Switching\PanelSwitcher;
use Gcego\Tables\Support\Icons;

/**
 * Builds the PanelShell of the current request's panel (null outside panels).
 */
final readonly class PanelShellFactory
{
    public function __construct(
        private PanelContextResolver $contexts,
        private PanelDefinitions $panels,
        private PanelRoutes $routes,
        private PanelSwitcher $switcher,
        private UserMenu $userMenu,
        private PanelAssetResolver $assets,
        private PanelsConfig $config,
        private ChromaManager $styling,
        private Icons $icons,
    ) {}

    public function current(string $title): ?PanelShell
    {
        $context = $this->contexts->current();

        return $context === null ? null : $this->make($context, $title);
    }

    public function make(PanelContext $context, string $title): PanelShell
    {
        $panel = $this->panels->resolve($context->name());
        $branding = $panel->branding();

        return new PanelShell(
            context: $context,
            panel: $panel,
            state: new PanelState(
                layout: $panel->layout() ?? $this->config->defaultLayout,
                theme: $panel->theme() ?? $this->config->defaultTheme,
                collapsible: $this->config->collapsibleSidebar,
                storageKey: 'gcego.panels.'.$context->name().'.collapsed',
            ),
            title: $title,
            branding: $branding,
            homeUrl: $branding?->homeUrl() ?? $this->routes->url($this->routes->homeRoute($context->panel)),
            navigation: $panel->navigationName(),
            switcher: $this->switcher->links($context),
            userMenu: $this->userMenu->links($context),
            vite: $this->assets->vite($panel),
            userName: $this->userName($context),
            tenantName: $context->tenant === null ? null : ($context->tenant->name ?? (string) $context->tenant->key),
            styling: $this->styling,
            icons: $this->icons,
            userEmail: $this->userEmail($context),
            userAvatar: $this->userAvatar($context),
        );
    }

    private function userEmail(PanelContext $context): ?string
    {
        $email = $context->user->email ?? null;

        return is_string($email) && $email !== '' && $email !== $this->userName($context) ? $email : null;
    }

    /**
     * The user's picture: an avatar_url or profile_photo_url attribute that is a root-relative or https URL.
     */
    private function userAvatar(PanelContext $context): ?string
    {
        foreach (['avatar_url', 'profile_photo_url'] as $attribute) {
            $url = $context->user->{$attribute} ?? null;

            if (SafeUrl::asset($url)) {
                return $url;
            }
        }

        return null;
    }

    private function userName(PanelContext $context): string
    {
        foreach (['name', 'email'] as $attribute) {
            $value = $context->user->{$attribute} ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return __('gcego-panels::messages.account');
    }
}
