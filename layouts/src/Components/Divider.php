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
 * A separator:
 *
 *     new Divider()                                             // decorative rule (hidden from screen readers)
 *     new Divider('Or', …)                                      // labelled
 *     new Divider(new Property(Divider::Decorative, false))     // a thematic break (<hr>)
 *     new Divider(new Property(Divider::Orientation, 'vertical'))   // between items of a Row
 *
 * Spacing: none, sm, md (default) or lg.
 */
final class Divider extends LayoutComponent
{
    public const string Orientation = 'orientation';

    public const string Spacing = 'spacing';

    public const string Decorative = 'decorative';

    public function view(): string
    {
        return 'gcego-layouts::layouts.divider';
    }

    public function viewData(LayoutView $layout): array
    {
        $orientation = $this->stringProperty(self::Orientation) ?? 'horizontal';

        return [
            'vertical' => $orientation === 'vertical',
            'decorative' => $this->get(self::Decorative) !== false,
            'spacing' => LayoutPresets::DividerSpacing[$orientation][$this->stringProperty(self::Spacing) ?? 'md'],
        ];
    }

    public function hidesWhenEmpty(): bool
    {
        return false;
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withProperties(
                self::choice(self::Orientation, ['horizontal', 'vertical'], 'horizontal'),
                self::choice(self::Spacing, array_keys(LayoutPresets::DividerSpacing['horizontal']), 'md'),
                new PropertyDefinition(self::Decorative, Type::bool(), default: true),
            )
            ->withoutChildren();
    }
}
