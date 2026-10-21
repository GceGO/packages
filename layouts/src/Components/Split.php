<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Rendering\LayoutView;

/**
 * Main content beside secondary content:
 *
 *     new Split(new Main(new Table(…)), new Aside('Activity', new ActivityFeed(…)))
 *     new Split(new Property(Split::Ratio, '3:1'), new Property(Split::StackBelow, 'xl'), …)
 *
 * Ratio (first region : second region): 1:1, 2:1 (default), 3:1, 3:2, 1:2,
 * 1:3, 2:3. Side by side from StackBelow (md, lg (default) or xl), stacked
 * below it. It is a grid of equal minmax(0, 1fr) columns, so wide content
 * (tables) scrolls inside its region instead of overflowing the page.
 */
final class Split extends Regions
{
    public const string Ratio = 'ratio';

    public function view(): string
    {
        return 'gcego-layouts::layouts.split';
    }

    public function viewData(LayoutView $layout): array
    {
        $breakpoint = $this->stackBelow();

        return [
            'split' => implode(' ', [...LayoutPresets::SplitBase, count($layout->regions) === 2 ? LayoutPresets::GridColumns[$breakpoint][$this->ratio()[0]] : '', $this->gapClasses($layout->config->defaultGap)]),
            ...$this->regionData($layout),
        ];
    }

    /**
     * The grid columns and the span of the first and second region.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public function ratio(): array
    {
        return LayoutPresets::SplitRatios[$this->stringProperty(self::Ratio) ?? '2:1'];
    }

    protected function regionClasses(LayoutView $layout): array
    {
        $breakpoint = $this->stackBelow();
        $ratio = $this->ratio();
        $classes = [];

        foreach ($layout->regions as $index => $region) {
            $span = count($layout->regions) === 2 ? $ratio[$index + 1] : 1;
            $classes[] = trim(($span > 1 ? LayoutPresets::ColumnSpans[$breakpoint][$span] : '').' '.($region->component instanceof Aside && $region->component->isSticky() ? LayoutPresets::StickyRegion[$breakpoint] : ''));
        }

        return $classes;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withProperties(
                self::choice(self::Ratio, array_keys(LayoutPresets::SplitRatios), '2:1'),
                self::choice(self::StackBelow, array_keys(LayoutPresets::StickyRegion), 'lg'),
            )
            ->withAllowedChildren([Main::class, Aside::class], 2, 2)
            ->withConstraint(self::regionErrors(...));
    }
}
