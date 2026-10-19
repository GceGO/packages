<?php

declare(strict_types=1);

namespace Gcego\Forms\Layout;

/**
 * Tabbed panels (WAI-ARIA tabs pattern: arrow keys move between tabs).
 * Every panel stays in the form: hidden tabs are still validated and saved.
 *
 *     new Tabs(new Tab('Details', ...), new Tab('Schedule', ...))
 */
final class Tabs extends LayoutComponent
{
    public function view(): string
    {
        return 'gcego-forms::layouts.tabs';
    }

    protected static function allowedChildren(): array
    {
        return [Tab::class];
    }
}
