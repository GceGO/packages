<?php

declare(strict_types=1);

namespace Gcego\Studio\Tests\Fixtures\Extensions;

use Gcego\Panels\Contracts\PanelExtension;
use Gcego\Panels\Registry\PanelDescriptor;
use Gcego\Panels\Shells\PanelShell;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Routing\Router;
use Illuminate\Support\HtmlString;

final class ReportsPanelExtension implements PanelExtension
{
    public function routes(Router $router, PanelDescriptor $panel): void
    {
        $router->get('acme-reports', static fn (): string => 'Acme reports')->name('acme-reports');
    }

    public function topbar(PanelShell $shell): Htmlable
    {
        return new HtmlString('<span data-acme-reports>Acme</span>');
    }

    public function body(PanelShell $shell): ?Htmlable
    {
        return null;
    }
}
