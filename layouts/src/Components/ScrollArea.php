<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Rendering\LayoutView;
use Gcego\Rendering\Context\RenderContext;

/**
 * A region that scrolls natively when its content is larger than it:
 *
 *     new ScrollArea('Audit log', new Property(ScrollArea::MaxHeight, 'lg'), new ActivityFeed(…))
 *     new ScrollArea('Timetable', new Property(ScrollArea::Direction, 'horizontal'), …)
 *
 * Keyboard users can focus it and scroll with the arrow keys; it is a
 * labelled region (Label, or "Scrollable content"). Direction: vertical
 * (default), horizontal or both. MaxHeight: sm (16rem), md (24rem, default),
 * lg (32rem), xl (40rem) or none.
 */
final class ScrollArea extends LayoutComponent
{
    public const string Direction = 'direction';

    public const string MaxHeight = 'max_height';

    public function view(): string
    {
        return 'gcego-layouts::layouts.scroll-area';
    }

    public function viewData(LayoutView $layout): array
    {
        return ['scroll' => trim(implode(' ', LayoutPresets::ScrollArea).' '.LayoutPresets::ScrollDirections[$this->stringProperty(self::Direction) ?? 'vertical'].' '.LayoutPresets::ScrollHeights[$this->stringProperty(self::MaxHeight) ?? 'md'])];
    }

    public function childHeadingLevel(RenderContext $context): int
    {
        return $context->headingLevel;
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            self::choice(self::Direction, array_keys(LayoutPresets::ScrollDirections), 'vertical'),
            self::choice(self::MaxHeight, array_keys(LayoutPresets::ScrollHeights), 'md'),
        );
    }
}
