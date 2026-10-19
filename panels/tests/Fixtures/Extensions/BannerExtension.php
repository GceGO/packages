<?php

declare(strict_types=1);

namespace Gcego\Panels\Tests\Fixtures\Extensions;

use Gcego\Panels\Contracts\PanelExtension;
use Gcego\Panels\PanelManager;
use Gcego\Panels\Registry\PanelDescriptor;
use Gcego\Panels\Shells\PanelShell;
use Illuminate\Routing\Router;
use Illuminate\Support\HtmlString;

final class BannerExtension implements PanelExtension
{
    public function routes(Router $router, PanelDescriptor $panel): void
    {
        $router->get('banner', static fn () => app(PanelManager::class)->respond('Banner', new HtmlString('<p>Banner of '.e($panel->title).'</p>')))->name('banner');
    }

    public function topbar(PanelShell $shell): HtmlString
    {
        return new HtmlString('<span data-banner-topbar>'.e($shell->context->name()).'</span>');
    }

    public function body(PanelShell $shell): HtmlString
    {
        return new HtmlString('<div data-banner-body></div>');
    }
}
