<?php

declare(strict_types=1);

namespace Gcego\Forms\Layout;

/**
 * One panel of Tabs: new Tab('Details', ...fields)
 */
final class Tab extends LayoutComponent
{
    public function view(): string
    {
        return 'gcego-forms::layouts.tab';
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }
}
