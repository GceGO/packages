<?php

declare(strict_types=1);

namespace Gcego\Infolists\Layout;

/**
 * Entries in 1 to 4 columns (one column on small screens):
 *
 *     new Grid(2, new Text('name', 'Name'), new Text('category.name', 'Category'))
 *
 * Entries span columns with Entry::ColumnSpan. Class names come from a fixed
 * map, so Tailwind sees every one at build time.
 */
final class Grid extends LayoutComponent
{
    public function view(): string
    {
        return 'gcego-infolists::layouts.grid';
    }

    protected static function shorthand(): array
    {
        return [self::Columns];
    }
}
