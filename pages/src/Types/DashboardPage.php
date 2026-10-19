<?php

declare(strict_types=1);

namespace Gcego\Pages\Types;

use Gcego\Components\ComponentDefinition;
use Gcego\Pages\Page;

/**
 * A page of gcego/widgets widgets arranged with gcego/layouts. Each widget
 * keeps its own authorization, data and (lazy) Livewire host; widgets that
 * need no interaction render as plain HTML:
 *
 *     new DashboardPage(
 *         new Property(DashboardPage::Name, 'dashboard'),
 *         new Property(DashboardPage::Title, 'Dashboard'),
 *         new Authenticated,
 *         new Grid(new Columns(3), new Stat('Students', StudentCount::class, new Authenticated), …),
 *     )
 */
class DashboardPage extends Page
{
    public static function pageType(): string
    {
        return 'dashboard';
    }

    protected static function recordPage(): bool
    {
        return false;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $definition = parent::defineSchema();

        return $definition->withAllowedChildren($definition->allowedChildren, 1);
    }
}
