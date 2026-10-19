<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Rendering\LayoutView;
use Gcego\Layouts\Responsive\ResponsiveValue;
use Throwable;

/**
 * Extra space, on the spacing scale (2, 4, 6, 8, 12, 16):
 *
 *     new Spacer(8)
 *     new Spacer(['default' => 4, 'lg' => 12])
 *     new Spacer(new Property(Spacer::Grow, true))   // in a Row: pushes the following children to the end
 *
 * It sets the flex basis, so it is vertical space in a stack, a section body
 * or a layout, and horizontal space in a row. Decorative (aria-hidden).
 */
final class Spacer extends LayoutComponent
{
    public const string Size = 'size';

    public const string Grow = 'grow';

    public function view(): string
    {
        return 'gcego-layouts::layouts.spacer';
    }

    public function viewData(LayoutView $layout): array
    {
        return ['spacer' => implode(' ', $this->boolProperty(self::Grow) ? [...LayoutPresets::Spacer, ...LayoutPresets::Grow] : [...LayoutPresets::Spacer, $this->size()->classes(LayoutPresets::SpacerSizes)])];
    }

    public function size(): ResponsiveValue
    {
        return self::sizeOf($this->get(self::Size) ?? 4);
    }

    public function hidesWhenEmpty(): bool
    {
        return false;
    }

    protected static function shorthand(): array
    {
        return [self::Size];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withProperties(
                new PropertyDefinition(self::Size, [Type::int(), Type::array()], default: 4, rule: static function (mixed $value): bool {
                    try {
                        self::sizeOf($value);

                        return true;
                    } catch (Throwable) {
                        return false;
                    }
                }, ruleDescription: 'a spacing step or a breakpoint map of steps'),
                new PropertyDefinition(self::Grow, Type::bool(), default: false),
            )
            ->withoutChildren();
    }

    private static function sizeOf(mixed $value): ResponsiveValue
    {
        return ResponsiveValue::of($value, static fn (mixed $step): bool => is_int($step) && in_array($step, LayoutPresets::SpacerSteps, true), 'Spacer', 'a spacing step ('.implode(', ', LayoutPresets::SpacerSteps).')');
    }
}
