<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Rendering\LayoutView;

/**
 * Constrains content to a readable width, centred, with responsive padding:
 *
 *     new Container(new Property(Container::MaxWidth, '5xl'), new Section(…))
 *
 * MaxWidth: sm … 7xl, prose or full (default: gcego-layouts.container.default_max_width).
 * Fluid: full width. Padding: none, sm, md (default), lg. Element: div,
 * section, article, main, header or footer.
 */
final class Container extends LayoutComponent
{
    public const string MaxWidth = 'max_width';

    public const string Fluid = 'fluid';

    public const string Padding = 'padding';

    public const string Centered = 'centered';

    public const string Element = 'element';

    public const array Elements = ['div', 'section', 'article', 'main', 'header', 'footer'];

    public function view(): string
    {
        return 'gcego-layouts::layouts.container';
    }

    public function viewData(LayoutView $layout): array
    {
        $width = $this->boolProperty(self::Fluid) ? 'full' : ($this->stringProperty(self::MaxWidth) ?? $layout->config->defaultMaxWidth);

        return [
            'element' => $this->stringProperty(self::Element) ?? 'div',
            'container' => trim(implode(' ', $this->get(self::Centered) === false ? LayoutPresets::FullWidth : LayoutPresets::Centered).' '.LayoutPresets::MaxWidths[$width].' '.LayoutPresets::ContainerPaddings[$this->stringProperty(self::Padding) ?? 'md']),
        ];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            self::choice(self::MaxWidth, array_keys(LayoutPresets::MaxWidths)),
            new PropertyDefinition(self::Fluid, Type::bool(), default: false),
            self::choice(self::Padding, array_keys(LayoutPresets::ContainerPaddings), 'md'),
            new PropertyDefinition(self::Centered, Type::bool(), default: true),
            self::choice(self::Element, self::Elements, 'div'),
        );
    }
}
