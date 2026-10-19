<?php

declare(strict_types=1);

namespace Gcego\Layouts\Rendering;

use Gcego\Chroma\ChromaManager;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\LayoutsConfig;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Styling\StyleSlots;
use Gcego\Tables\Support\Icons;
use Illuminate\Support\HtmlString;

/**
 * What a layout view receives as $layout: the component occurrence, its
 * rendered children (items) and the regions it renders itself (tabs, split
 * regions, header actions), each a LayoutView of its own.
 *
 *     <div id="{{ $layout->id() }}"{{ $layout->class('root', $grid) }}{{ $layout->key() }}>{{ $layout->content() }}</div>
 *
 * class() merges the computed layout classes with the component's styling
 * slot (package defaults, theme and app overrides, Tailwind nodes) through
 * gcego/chroma, so a Tailwind node can override any computed class.
 */
final readonly class LayoutView
{
    /**
     * @param  list<LayoutItem>  $items
     * @param  list<LayoutView>  $regions
     */
    public function __construct(
        public LayoutComponent $component,
        public RenderContext $context,
        public array $items,
        public array $regions,
        public LayoutsConfig $config,
        private StyleSlots $styles,
        private ChromaManager $styling,
        private Icons $icons,
    ) {}

    public function isEmpty(): bool
    {
        return $this->items === [] && $this->regions === [];
    }

    /**
     * The rendered children, in source order.
     */
    public function content(): HtmlString
    {
        return new HtmlString(implode('', array_map(static fn (LayoutItem $item): string => $item->toHtml(), $this->items)));
    }

    /**
     * ` class="…"`: computed classes merged with a styling slot.
     */
    public function class(string $slot, string ...$computed): HtmlString
    {
        $classes = $this->classes($slot, ...$computed);

        return new HtmlString($classes === '' ? '' : ' class="'.e($classes).'"');
    }

    public function classes(string $slot, string ...$computed): string
    {
        return $this->styling->merge(trim(implode(' ', $computed)), ($this->styles)($slot));
    }

    /**
     * A stable DOM id for this occurrence (optionally suffixed).
     */
    public function id(string $suffix = ''): string
    {
        return $this->context->domId($suffix);
    }

    /**
     * ` wire:key="…"` in Livewire renders, so morphing matches this element.
     */
    public function key(): HtmlString
    {
        return new HtmlString($this->context->isLivewire() ? ' wire:key="'.e($this->id()).'"' : '');
    }

    public function live(): bool
    {
        return $this->context->isLivewire();
    }

    public function level(): int
    {
        return $this->context->headingLevel;
    }

    public function title(): ?string
    {
        return $this->component->title();
    }

    public function description(): ?string
    {
        return $this->component->description();
    }

    public function icon(string $class = 'size-5'): ?HtmlString
    {
        $icon = $this->component->icon();

        return $icon === null || ! $this->icons->has($icon) ? null : $this->icons->svg($icon, $class);
    }

    /**
     * The regions of a class (Actions, Footer, Tab, …), in source order.
     *
     * @param  class-string<LayoutComponent>  $class
     * @return list<LayoutView>
     */
    public function regions(string $class): array
    {
        return array_values(array_filter($this->regions, static fn (self $region): bool => $region->component instanceof $class));
    }

    /**
     * @param  class-string<LayoutComponent>  $class
     */
    public function region(string $class): ?self
    {
        return $this->regions($class)[0] ?? null;
    }
}
