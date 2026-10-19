<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Layouts\Configuration\Align;
use Gcego\Layouts\Configuration\Gap;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Rendering\LayoutView;

/**
 * The footer of a Section or Card (below the body, separated by a rule):
 *
 *     new Card('Fees', …, new Footer(new Actions(new Action('view_all', 'View all'))))
 */
final class Footer extends LayoutComponent
{
    public function view(): string
    {
        return 'gcego-layouts::layouts.footer';
    }

    public function viewData(LayoutView $layout): array
    {
        return ['footer' => implode(' ', LayoutPresets::Footer)];
    }

    protected static function layoutConfigurations(): array
    {
        return [...parent::layoutConfigurations(), Gap::class, Align::class];
    }
}
