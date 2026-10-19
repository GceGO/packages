<?php

declare(strict_types=1);

namespace Gcego\Settings\Integration;

use Gcego\Panels\Contracts\PanelExtension;
use Gcego\Panels\PanelManager;
use Gcego\Panels\Registry\PanelDescriptor;
use Gcego\Panels\Shells\PanelShell;
use Gcego\Settings\SettingsConfig;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Routing\Router;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * The settings screen in every gcego/panels panel: "<panel>/settings"
 * (route "<namespace>settings", the first page the viewer may open) and
 * "<panel>/settings/{page}" (route "<namespace>settings.page"), behind
 * the panel's authentication, tenancy and access checks. Pages the viewer
 * may not open here, or that need a tenant the request does not have, are 404.
 */
final readonly class PanelSettingsExtension implements PanelExtension
{
    public const string RouteName = 'settings';

    public function __construct(private SettingsConfig $config) {}

    public function routes(Router $router, PanelDescriptor $panel): void
    {
        if (! $this->config->enabled || ! $this->config->panels) {
            return;
        }

        $router->get($this->config->panelPath, static fn (PanelManager $panels) => $panels->respond(
            (string) __('gcego-settings::messages.settings'),
            new HtmlString((string) Livewire::mount('gcego-settings-panel', ['link' => url()->current().'/:page'], 'gcego-settings-panel')),
        ))->name(self::RouteName);

        $router->get($this->config->panelPath.'/{page}', static fn (PanelManager $panels, string $page) => $panels->respond(
            (string) __('gcego-settings::messages.settings'),
            new HtmlString((string) Livewire::mount('gcego-settings-panel', ['page' => $page, 'link' => Str::beforeLast(url()->current(), '/').'/:page'], 'gcego-settings-panel')),
        ))->where('page', '[a-z][a-z0-9_-]{0,63}')->name(self::RouteName.'.page');
    }

    public function topbar(PanelShell $shell): ?Htmlable
    {
        return null;
    }

    public function body(PanelShell $shell): ?Htmlable
    {
        return null;
    }
}
