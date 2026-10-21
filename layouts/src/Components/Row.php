<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Layouts\Configuration\Align;
use Gcego\Layouts\Configuration\Gap;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Rendering\LayoutView;

/**
 * Children side by side (flex row), wrapping by default:
 *
 *     new Row(new Align(justify: 'between'), new Stat(…), new Stat(…))
 *     new Row(new Property(Row::StackBelow, 'md'), …)   // stacked on phones, a row from md
 *
 * Default gap 3 (0.75rem); children centred vertically (stretched while stacked).
 */
final class Row extends LayoutComponent
{
    public const string Wrap = 'wrap';

    public const string StackBelow = 'stack_below';

    public const string Element = 'element';

    public const int DefaultGap = 3;

    public function view(): string
    {
        return 'gcego-layouts::layouts.row';
    }

    public function viewData(LayoutView $layout): array
    {
        $stack = $this->stringProperty(self::StackBelow);
        $direction = $stack === null ? implode(' ', LayoutPresets::RowDirection) : implode(' ', LayoutPresets::RowStacked).' '.LayoutPresets::RowFrom[$stack].' '.LayoutPresets::Items[$stack]['center'];

        return [
            'element' => $this->stringProperty(self::Element) ?? 'div',
            'row' => implode(' ', [...LayoutPresets::RowBase, $direction, implode(' ', $this->get(self::Wrap) === false ? LayoutPresets::NoWrap : LayoutPresets::Wrap), $this->gapClasses(self::DefaultGap), $this->alignClasses()]),
        ];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Wrap, Type::bool(), default: true),
            self::choice(self::StackBelow, array_keys(LayoutPresets::RowFrom)),
            self::choice(self::Element, ['div', 'nav', 'header', 'footer'], 'div'),
        );
    }

    protected static function layoutConfigurations(): array
    {
        return [...parent::layoutConfigurations(), Gap::class, Align::class];
    }
}
