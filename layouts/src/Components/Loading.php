<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Chroma\Tailwind;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Rendering\LayoutView;

/**
 * A skeleton placeholder of stable size (for lazy content, so the page does
 * not shift when it arrives), announced politely as "Loading…" (or Label):
 *
 *     new Loading()                                        // three text lines
 *     new Loading(new Property(Loading::Variant, 'card'))  // card, grid (three tiles) or table (rows)
 *
 * The pulse is disabled for users who prefer reduced motion.
 */
final class Loading extends LayoutComponent
{
    public const string Variant = 'variant';

    public const string Lines = 'lines';

    public const string SkeletonSlot = 'skeleton';

    public static function styleSlots(): array
    {
        return [Tailwind::Wrapper => 'The outer element.', self::SkeletonSlot => 'Each skeleton block.'];
    }

    public function view(): string
    {
        return 'gcego-layouts::layouts.loading';
    }

    public function viewData(LayoutView $layout): array
    {
        $variant = $this->stringProperty(self::Variant) ?? 'lines';
        $lines = $this->get(self::Lines);
        $count = is_int($lines) ? $lines : 3;
        $blocks = match ($variant) {
            'lines' => array_map(static fn (int $line): string => LayoutPresets::SkeletonLines[$line % count(LayoutPresets::SkeletonLines)], range(0, $count - 1)),
            'grid' => array_fill(0, 3, LayoutPresets::SkeletonVariants['grid']),
            'table' => array_fill(0, $count, LayoutPresets::SkeletonVariants['table']),
            default => [LayoutPresets::SkeletonVariants['card']],
        };

        return ['blocks' => $blocks, 'grid' => $variant === 'grid'];
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
                self::choice(self::Variant, ['lines', 'card', 'grid', 'table'], 'lines'),
                new PropertyDefinition(self::Lines, Type::int(), default: 3, rule: static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= 12, ruleDescription: '1 to 12'),
            )
            ->withoutChildren();
    }
}
