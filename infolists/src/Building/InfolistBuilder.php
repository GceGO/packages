<?php

declare(strict_types=1);

namespace Gcego\Infolists\Building;

use Gcego\Components\Building\ComponentBuilder;
use Gcego\Infolists\Layout\Grid;
use Gcego\Infolists\Layout\Section;
use Gcego\Infolists\Layout\Split;
use Gcego\Infolists\Layout\Tab;
use Gcego\Infolists\Layout\Tabs;

/**
 * The fluent form of an Infolist. Besides its own settings, it makes
 * layouts, so an infolist callback reads top to bottom:
 *
 *     fn (InfolistBuilder $infolist) => $infolist->section('Teacher')->columns(3)->schema([...])
 *
 * section(), grid(), tabs(), tab() and split() return new layout builders;
 * they do not change the infolist.
 */
class InfolistBuilder extends ComponentBuilder
{
    public function section(?string $heading = null): ComponentBuilder
    {
        return Section::define($heading);
    }

    public function grid(int $columns = 2): ComponentBuilder
    {
        return Grid::define($columns);
    }

    public function tabs(): ComponentBuilder
    {
        return Tabs::define();
    }

    public function tab(string $heading): ComponentBuilder
    {
        return Tab::define($heading);
    }

    public function split(): ComponentBuilder
    {
        return Split::define();
    }
}
