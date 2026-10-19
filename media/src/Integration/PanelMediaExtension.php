<?php

declare(strict_types=1);

namespace Gcego\Media\Integration;

use Gcego\Media\MediaConfig;
use Gcego\Panels\Contracts\PanelExtension;
use Gcego\Panels\PanelManager;
use Gcego\Panels\Registry\PanelDescriptor;
use Gcego\Panels\Shells\PanelShell;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Routing\Router;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;

/**
 * The media library in every gcego/panels panel at "<panel>/media"
 * (route "<namespace>media"), behind the panel's authentication, tenancy
 * and access checks. What it lists is still decided per file (MediaAccess).
 */
final readonly class PanelMediaExtension implements PanelExtension
{
    public const string RouteName = 'media';

    public function __construct(private MediaConfig $config) {}

    public function routes(Router $router, PanelDescriptor $panel): void
    {
        if ($this->config->panels) {
            $router->get(self::RouteName, static fn (PanelManager $panels) => $panels->respond((string) __('gcego-media::messages.library'), new HtmlString((string) Livewire::mount('gcego-media-library', [], 'gcego-media-library'))))->name(self::RouteName);
        }
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
