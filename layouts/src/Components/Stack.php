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
 * Children one above the other:
 *
 *     new Stack(new Gap(['default' => 4, 'lg' => 6]), …)
 *     new Stack(new Property(Stack::Divided, true), …)   // a rule between children
 *
 * Default gap 4 (1rem).
 */
final class Stack extends LayoutComponent
{
    public const string Divided = 'divided';

    public const string Element = 'element';

    public const int DefaultGap = 4;

    public function view(): string
    {
        return 'gcego-layouts::layouts.stack';
    }

    public function viewData(LayoutView $layout): array
    {
        $spacing = $this->boolProperty(self::Divided) ? implode(' ', LayoutPresets::Divided) : $this->gapClasses(self::DefaultGap);

        return [
            'element' => $this->stringProperty(self::Element) ?? 'div',
            'stack' => implode(' ', [...LayoutPresets::Flow, $spacing, $this->alignClasses()]),
        ];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Divided, Type::bool(), default: false),
            self::choice(self::Element, ['div', 'section', 'article'], 'div'),
        );
    }

    protected static function layoutConfigurations(): array
    {
        return [...parent::layoutConfigurations(), Gap::class, Align::class];
    }
}
