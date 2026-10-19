<?php

declare(strict_types=1);

namespace Gcego\Pages\Rendering;

use Gcego\Chroma\ChromaManager;
use Gcego\Navigation\Breadcrumbs\BreadcrumbCollection;
use Gcego\Pages\Context\PageContext;
use Gcego\Pages\Presets\PagePresets;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Styling\StyleSlots;
use Illuminate\Support\HtmlString;

/**
 * What a page view needs: the open page, its rendered regions and class
 * helpers (preset classes merged with the page's Tailwind slots through
 * gcego/chroma, memoized per render).
 */
final class PageView
{
    /**
     * @var array<string, string>
     */
    private array $merged = [];

    /**
     * @param  list<HtmlString>  $actions
     * @param  list<HtmlString>  $body
     */
    public function __construct(
        public readonly PageContext $page,
        public readonly RenderContext $context,
        public readonly array $actions,
        public readonly array $body,
        public readonly string $width,
        private readonly StyleSlots $styles,
        private readonly ChromaManager $styling,
    ) {}

    public function title(): string
    {
        return $this->page->header->title->text ?? $this->page->page->pageTitle();
    }

    public function description(): ?string
    {
        return $this->page->header?->description?->text;
    }

    public function icon(): ?string
    {
        return $this->page->header?->icon;
    }

    public function breadcrumbs(): BreadcrumbCollection
    {
        return $this->page->header->breadcrumbs ?? new BreadcrumbCollection;
    }

    public function isEmpty(): bool
    {
        return $this->body === [];
    }

    public function emptyText(): string
    {
        return (string) __($this->page->page->emptyText() ?? 'gcego-pages::messages.empty');
    }

    public function id(string $suffix): string
    {
        return $this->context->domId($suffix);
    }

    /**
     * ` class="…"` of a page slot over its preset classes.
     *
     * @param  list<string>  $preset
     */
    public function class(string $slot, array $preset, string ...$extra): HtmlString
    {
        $classes = $this->classes($slot, $preset, ...$extra);

        return new HtmlString($classes === '' ? '' : ' class="'.e($classes).'"');
    }

    /**
     * @param  list<string>  $preset
     */
    public function classes(string $slot, array $preset, string ...$extra): string
    {
        $computed = trim(implode(' ', [...$preset, ...$extra]));
        $own = ($this->styles)($slot);

        return $this->merged[$computed."\0".$own] ??= $own === '' ? $computed : $this->styling->merge($computed, $own);
    }

    public function widthClass(): string
    {
        return PagePresets::Widths[$this->width] ?? PagePresets::Widths['standard'];
    }
}
