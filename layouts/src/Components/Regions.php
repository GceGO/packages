<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Layouts\Configuration\Gap;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\Rendering\LayoutView;

/**
 * Shared by Split and Sidebar: exactly one Main and one Aside, side by side
 * from a breakpoint and stacked below it. Their order is the source order,
 * visually and for keyboard and screen-reader users: declare the Aside first
 * to put it on the start (left in left-to-right languages) side.
 */
abstract class Regions extends LayoutComponent
{
    public const string StackBelow = 'stack_below';

    public function ownsRegion(LayoutComponent $child): bool
    {
        return $child instanceof Main || $child instanceof Aside;
    }

    public function stackBelow(): string
    {
        return $this->stringProperty(self::StackBelow) ?? 'lg';
    }

    /**
     * Classes of each rendered region, in order. When only one region renders
     * (the other is empty or not authorized), it takes the full width.
     *
     * @return list<string>
     */
    abstract protected function regionClasses(LayoutView $layout): array;

    /**
     * @return array<string, mixed>
     */
    protected function regionData(LayoutView $layout): array
    {
        return ['regionClasses' => $this->regionClasses($layout)];
    }

    /**
     * @return list<string>
     */
    protected static function regionErrors(ComponentInterface $component): array
    {
        $mains = count(array_filter($component->children()->all(), static fn (ComponentInterface $child): bool => $child instanceof Main));
        $asides = count(array_filter($component->children()->all(), static fn (ComponentInterface $child): bool => $child instanceof Aside));

        return $mains === 1 && $asides === 1 ? [] : ['it needs exactly one Main and one Aside.'];
    }

    protected static function layoutConfigurations(): array
    {
        return [...parent::layoutConfigurations(), Gap::class];
    }
}
