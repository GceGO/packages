<?php

declare(strict_types=1);

namespace Gcego\Panels\Contracts;

use Gcego\Panels\Registry\PanelDescriptor;
use Gcego\Panels\Shells\PanelShell;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Routing\Router;

/**
 * Trusted package code that adds to every panel (gcego/notifications'
 * bell, toasts and inbox): register it with PanelManager::extend() or
 * gcego-panels.extensions, while booting.
 *
 * - routes(): extra routes of the panel, registered inside the panel's
 *   group (its prefix, domain, middleware, panel access and route name
 *   namespace), so they are authorized like the panel's pages;
 * - topbar(): HTML placed in the shell's top bar, before the user menu;
 * - body(): HTML placed at the end of the document body.
 *
 * Returned HTML is trusted markup built by the extension (escape any data).
 */
interface PanelExtension
{
    public function routes(Router $router, PanelDescriptor $panel): void;

    public function topbar(PanelShell $shell): ?Htmlable;

    public function body(PanelShell $shell): ?Htmlable;
}
