<?php

declare(strict_types=1);

namespace Gcego\Infolists\Layout;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Infolists\Presets\InfolistPresets;

/**
 * Main content and an aside, stacked on small screens:
 *
 *     new Split(
 *         new Section('Details', ...),                 // main
 *         new Section('Record', ...),                  // aside
 *         new Property(Split::AsideWidth, 'md'),
 *     )
 *
 * Exactly two children; the reading order stays main, then aside.
 */
final class Split extends LayoutComponent
{
    /** sm | md | lg (aside width from the breakpoint up) */
    public const string AsideWidth = 'aside_width';

    /** md | lg: the breakpoint where the columns appear */
    public const string From = 'from';

    public function view(): string
    {
        return 'gcego-infolists::layouts.split';
    }

    public function gridClass(): string
    {
        $from = $this->get(self::From);
        $width = $this->get(self::AsideWidth);

        return InfolistPresets::SplitWidths[is_string($from) ? $from : 'lg'][is_string($width) ? $width : 'md'] ?? InfolistPresets::SplitWidths['lg']['md'];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withProperties(
                new PropertyDefinition(self::AsideWidth, Type::string(), default: 'md', allowedValues: ['sm', 'md', 'lg']),
                new PropertyDefinition(self::From, Type::string(), default: 'lg', allowedValues: ['md', 'lg']),
            )
            ->withAllowedChildren(self::allowedChildren(), 2, 2);
    }
}
