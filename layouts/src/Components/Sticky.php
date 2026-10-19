<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Rendering\LayoutView;

/**
 * Content that stays in view while its scroll container scrolls (CSS
 * position: sticky):
 *
 *     new Sticky(new Row(new Actions(…)))                                // stuck to the top
 *     new Sticky(new Property(Sticky::Position, 'bottom'), …)            // a bottom action bar
 *     new Sticky(new Property(Sticky::Offset, 16), new Property(Sticky::From, 'lg'), …)   // below a 4rem top bar, on large screens
 *
 * Offset (0, 2, 4, 6, 8, 12, 16) leaves room for fixed navigation above it;
 * Layer (base z-10, raised z-20, overlay z-30) keeps it under dialogs. It
 * has a surface background so content scrolling under it stays readable.
 * Limits: it sticks within its nearest scrolling ancestor and only while
 * its parent is in view (an ancestor with overflow: hidden stops it).
 */
final class Sticky extends LayoutComponent
{
    public const string Position = 'position';

    public const string Offset = 'offset';

    public const string From = 'from';

    public const string Layer = 'layer';

    public const string Surface = 'surface';

    public function view(): string
    {
        return 'gcego-layouts::layouts.sticky';
    }

    public function viewData(LayoutView $layout): array
    {
        $position = $this->stringProperty(self::Position) ?? 'top';
        $offset = $this->get(self::Offset);

        return ['sticky' => implode(' ', [
            LayoutPresets::StickyFrom[$this->stringProperty(self::From) ?? 'default'],
            LayoutPresets::StickyOffsets[$position][is_int($offset) ? $offset : 0],
            LayoutPresets::Layers[$this->stringProperty(self::Layer) ?? 'base'],
            ...($this->get(self::Surface) === false ? [] : LayoutPresets::StickySurface),
        ])];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            self::choice(self::Position, ['top', 'bottom'], 'top'),
            new PropertyDefinition(self::Offset, Type::int(), default: 0, allowedValues: array_keys(LayoutPresets::StickyOffsets['top'])),
            self::choice(self::From, array_keys(LayoutPresets::StickyFrom), 'default'),
            self::choice(self::Layer, array_keys(LayoutPresets::Layers), 'base'),
            new PropertyDefinition(self::Surface, Type::bool(), default: true),
        );
    }
}
