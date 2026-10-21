<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Rendering\LayoutView;

/**
 * A fixed-width side column beside fluid main content (a structural
 * sidebar; navigation menus belong to gcego/navigation):
 *
 *     new Sidebar(new Aside('Filters', …), new Main(new Table(…)))          // sidebar on the start side
 *     new Sidebar(new Property(Sidebar::Width, 'lg'), new Property(Sidebar::Sticky, true), new Main(…), new Aside(…))
 *
 * Width: sm (16rem), md (18rem, default), lg (20rem), xl (24rem). Sticky
 * keeps the sidebar in view while the main region scrolls (from the
 * breakpoint where they sit side by side). StackBelow: md, lg (default), xl.
 */
final class Sidebar extends Regions
{
    public const string Width = 'width';

    public const string Sticky = 'sticky';

    public function view(): string
    {
        return 'gcego-layouts::layouts.split';
    }

    public function viewData(LayoutView $layout): array
    {
        $breakpoint = $this->stackBelow();

        return [
            'split' => implode(' ', [...LayoutPresets::Flow, LayoutPresets::RowFrom[$breakpoint], $this->gapClasses($layout->config->defaultGap)]),
            ...$this->regionData($layout),
        ];
    }

    protected function regionClasses(LayoutView $layout): array
    {
        $breakpoint = $this->stackBelow();
        $sticky = $this->boolProperty(self::Sticky);
        $width = LayoutPresets::SidebarWidths[$breakpoint][$this->stringProperty(self::Width) ?? 'md'];

        return array_map(static fn (LayoutView $region): string => $region->component instanceof Aside
            ? trim(implode(' ', LayoutPresets::SidebarRegion).' '.$width.' '.($sticky || $region->component->isSticky() ? LayoutPresets::StickyRegion[$breakpoint] : ''))
            : implode(' ', LayoutPresets::Grow), $layout->regions);
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withProperties(
                self::choice(self::Width, array_keys(LayoutPresets::SidebarWidths['lg']), 'md'),
                new PropertyDefinition(self::Sticky, Type::bool(), default: false),
                self::choice(self::StackBelow, array_keys(LayoutPresets::SidebarWidths), 'lg'),
            )
            ->withAllowedChildren([Main::class, Aside::class], 2, 2)
            ->withConstraint(self::regionErrors(...));
    }
}
