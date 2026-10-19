<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Layouts\Configuration\Align;
use Gcego\Layouts\Configuration\Gap;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Rendering\LayoutView;

/**
 * Header actions of a Section or Card (placed in the header, after the
 * title), or an inline group of actions anywhere else:
 *
 *     new Section('Students', new Actions(new Create, new Action('export', 'Export')), new Table(…))
 *
 * Actions keep their own host and authorization (gcego/actions): an action
 * the user may not run renders nothing, and Actions with nothing in them
 * render nothing.
 */
final class Actions extends LayoutComponent
{
    public function view(): string
    {
        return 'gcego-layouts::layouts.actions';
    }

    public function viewData(LayoutView $layout): array
    {
        return ['actions' => implode(' ', LayoutPresets::Actions)];
    }

    protected static function layoutConfigurations(): array
    {
        return [...parent::layoutConfigurations(), Gap::class, Align::class];
    }
}
