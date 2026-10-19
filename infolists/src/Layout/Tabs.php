<?php

declare(strict_types=1);

namespace Gcego\Infolists\Layout;

use Gcego\Components\Contracts\ComponentInterface;

/**
 * WAI-ARIA tabs (arrow keys, Home and End):
 *
 *     new Tabs(new Tab('Overview', ...), new Tab('Academic', ...))
 *
 * Only tabs with visible content are shown; tab switching is client-side
 * (Alpine) and needs no server round trip.
 */
final class Tabs extends LayoutComponent
{
    public function view(): string
    {
        return 'gcego-infolists::layouts.tabs';
    }

    /**
     * @return list<class-string<ComponentInterface>>
     */
    protected static function allowedChildren(): array
    {
        return [Tab::class];
    }
}
