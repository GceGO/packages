<?php

declare(strict_types=1);

namespace Gcego\Tables\Presets;

/**
 * Literal Tailwind classes for gcego/tables (scanned by gcego.css through
 * "tables/src/Presets"). Slots reference them; views keep their own literals.
 */
final class TablePresets
{
    public const array Wrapper = ['relative', 'isolate', 'flex', 'flex-col', 'overflow-clip', 'rounded-gcego-lg', 'border', 'border-border', 'bg-card', 'text-card-foreground', 'shadow-gcego-card'];

    public const array Header = ['flex', 'flex-col', 'gap-1', 'border-b', 'border-border', 'px-5', 'py-4', 'sm:px-6'];

    public const array Toolbar = ['flex', 'flex-col', 'gap-3', 'border-b', 'border-border', 'px-4', 'py-3', 'sm:flex-row', 'sm:flex-wrap', 'sm:items-center', 'sm:justify-between', 'sm:px-5'];

    public const array Search = ['h-10', 'w-full', 'rounded-gcego', 'border', 'border-input', 'bg-surface', 'pl-10', 'pr-16', 'text-sm', 'text-foreground', 'shadow-xs', 'transition', 'placeholder:text-muted-foreground', 'hover:border-foreground/40', 'focus-visible:border-ring', 'focus-visible:outline-none', 'focus-visible:ring-4', 'focus-visible:ring-ring/15', '[&::-webkit-search-cancel-button]:hidden', 'sm:w-80'];

    public const array Filters = ['grid', 'grid-cols-1', 'gap-4', 'rounded-gcego-lg', 'border', 'border-border', 'bg-surface-muted/50', 'p-4', 'sm:grid-cols-2', 'lg:grid-cols-4'];

    /**
     * The scroll area: bounded height with a sticky header, contained overscroll and themed scrollbars:
     * rounded ::-webkit-scrollbar thumbs in Chromium and Safari (they ignore those rules when the standard
     * scrollbar-width/scrollbar-color are set, so those apply to Firefox only).
     */
    public const array Scroller = ['group/scroller', 'relative', 'overflow-auto', 'overscroll-x-contain', 'scroll-smooth', 'max-h-[min(72vh,56rem)]', 'supports-[-moz-appearance:none]:[scrollbar-width:thin]', 'supports-[-moz-appearance:none]:[scrollbar-color:var(--gcego-border-strong)_transparent]', '[&::-webkit-scrollbar]:size-2.5', '[&::-webkit-scrollbar-track]:bg-transparent', '[&::-webkit-scrollbar-corner]:bg-transparent', '[&::-webkit-scrollbar-thumb]:rounded-full', '[&::-webkit-scrollbar-thumb]:border-2', '[&::-webkit-scrollbar-thumb]:border-solid', '[&::-webkit-scrollbar-thumb]:border-transparent', '[&::-webkit-scrollbar-thumb]:bg-clip-padding', '[&::-webkit-scrollbar-thumb]:bg-border-strong/60', 'hover:[&::-webkit-scrollbar-thumb]:bg-border-strong'];

    public const array Table = ['w-full', 'min-w-full', 'border-separate', 'border-spacing-0', 'text-left', 'text-sm'];

    public const array TableHead = ['text-[11px]', 'font-semibold', 'uppercase', 'tracking-[0.06em]', 'text-muted-foreground'];

    public const array HeaderCell = ['sticky', 'top-0', 'z-10', 'border-b', 'border-border', 'bg-surface-muted/95', 'px-4', 'py-3', 'font-semibold', 'whitespace-nowrap', 'backdrop-blur-md', 'first:pl-5', 'last:pr-5'];

    public const array TableBody = ['[&>tr:last-child>td]:border-b-0'];

    public const array Row = ['group/row', 'transition-colors', 'duration-150', 'hover:bg-accent', 'aria-selected:bg-selected'];

    public const array Cell = ['border-b', 'border-border/70', 'px-4', 'py-3', 'align-middle', 'text-foreground', 'first:pl-5', 'last:pr-5'];

    public const array CompactCell = ['border-b', 'border-border/70', 'px-3', 'py-1.5', 'align-middle', 'text-foreground', 'first:pl-5', 'last:pr-5'];

    /** Sticky cells need an opaque background that follows the row's hover and selection. */
    public const array StickyCell = ['bg-card', 'group-hover/row:bg-accent', 'group-aria-selected/row:bg-selected'];

    public const array StickyStartShadow = ['group-data-[scrolled-x=true]/scroller:shadow-[8px_0_10px_-8px_var(--gcego-overlay)]'];

    public const array StickyEndShadow = ['group-data-[at-end=false]/scroller:shadow-[-8px_0_10px_-8px_var(--gcego-overlay)]'];

    public const array Footer = ['flex', 'flex-col', 'gap-3', 'border-t', 'border-border', 'bg-surface-muted/40', 'px-4', 'py-3', 'text-sm', 'text-muted-foreground', 'sm:flex-row', 'sm:items-center', 'sm:justify-between', 'sm:px-5'];

    public const array Pagination = ['flex', 'flex-wrap', 'items-center', 'gap-2'];

    public const array PageGroup = ['inline-flex', 'items-center', 'gap-0.5', 'rounded-gcego', 'border', 'border-border', 'bg-surface', 'p-0.5', 'shadow-xs'];

    public const array PageButton = ['inline-flex', 'h-8', 'min-w-8', 'items-center', 'justify-center', 'rounded-gcego-sm', 'px-2', 'text-sm', 'font-medium', 'tabular-nums', 'text-muted-foreground', 'transition', 'hover:bg-accent', 'hover:text-foreground', 'focus-visible:outline-2', 'focus-visible:outline-offset-1', 'focus-visible:outline-ring', 'disabled:pointer-events-none', 'disabled:opacity-40', 'aria-[current=page]:bg-primary', 'aria-[current=page]:text-primary-foreground', 'aria-[current=page]:shadow-sm'];

    public const array ToolButton = ['inline-flex', 'h-9', 'items-center', 'gap-2', 'rounded-gcego', 'border', 'border-border', 'bg-surface', 'px-3', 'text-sm', 'font-medium', 'whitespace-nowrap', 'text-foreground', 'shadow-xs', 'transition', 'hover:border-border-strong', 'hover:bg-accent', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-ring', 'aria-expanded:border-border-strong', 'aria-expanded:bg-accent', 'disabled:pointer-events-none', 'disabled:opacity-50'];

    public const array IconButton = ['inline-flex', 'size-9', 'shrink-0', 'items-center', 'justify-center', 'rounded-gcego', 'border', 'border-border', 'bg-surface', 'text-muted-foreground', 'shadow-xs', 'transition', 'hover:border-border-strong', 'hover:bg-accent', 'hover:text-foreground', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-ring', 'aria-pressed:bg-accent', 'aria-pressed:text-foreground', 'disabled:pointer-events-none', 'disabled:opacity-50'];

    public const array Segmented = ['inline-flex', 'items-center', 'gap-0.5', 'rounded-gcego', 'border', 'border-border', 'bg-surface-muted', 'p-0.5'];

    public const array SegmentButton = ['inline-flex', 'size-8', 'items-center', 'justify-center', 'rounded-gcego-sm', 'text-muted-foreground', 'transition', 'hover:text-foreground', 'focus-visible:outline-2', 'focus-visible:outline-ring', 'aria-pressed:bg-surface', 'aria-pressed:text-foreground', 'aria-pressed:shadow-sm'];

    public const array RowButton = ['inline-flex', 'min-h-8', 'items-center', 'gap-1.5', 'rounded-gcego-sm', 'px-2', 'text-sm', 'font-medium', 'text-muted-foreground', 'transition', 'hover:bg-accent', 'hover:text-foreground', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-ring'];

    public const array DangerRowButton = ['inline-flex', 'min-h-8', 'items-center', 'gap-1.5', 'rounded-gcego-sm', 'px-2', 'text-sm', 'font-medium', 'text-muted-foreground', 'transition', 'hover:bg-danger-soft', 'hover:text-danger', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-ring'];

    public const array RowIconButton = ['inline-flex', 'size-8', 'items-center', 'justify-center', 'rounded-gcego-sm', 'text-muted-foreground', 'transition', 'hover:bg-surface', 'hover:text-foreground', 'hover:shadow-xs', 'hover:ring-1', 'hover:ring-border', 'focus-visible:outline-2', 'focus-visible:outline-offset-1', 'focus-visible:outline-ring'];

    public const array DangerRowIconButton = ['inline-flex', 'size-8', 'items-center', 'justify-center', 'rounded-gcego-sm', 'text-muted-foreground', 'transition', 'hover:bg-danger-soft', 'hover:text-danger', 'focus-visible:outline-2', 'focus-visible:outline-offset-1', 'focus-visible:outline-ring'];

    public const array SortButton = ['group/sort', '-mx-1.5', 'inline-flex', 'items-center', 'gap-1', 'rounded-gcego-sm', 'px-1.5', 'py-0.5', 'uppercase', 'tracking-[0.06em]', 'transition', 'hover:bg-accent', 'hover:text-foreground', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-ring', 'aria-[sort]:text-foreground'];

    public const array Checkbox = ['size-4', 'cursor-pointer', 'rounded-[5px]', 'border-input', 'accent-primary', 'transition', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-ring', 'disabled:cursor-not-allowed', 'disabled:opacity-40'];

    public const array Panel = ['absolute', 'right-0', 'z-40', 'mt-2', 'w-80', 'max-w-[calc(100vw-2rem)]', 'origin-top-right', 'rounded-gcego-lg', 'border', 'border-border', 'bg-popover', 'p-2', 'text-popover-foreground', 'shadow-gcego-overlay'];

    public const array PanelHeading = ['px-2', 'pb-2', 'pt-1', 'text-[11px]', 'font-semibold', 'uppercase', 'tracking-[0.06em]', 'text-muted-foreground'];

    public const array FilterControl = ['h-10', 'w-full', 'rounded-gcego', 'border', 'border-input', 'bg-surface', 'px-3', 'text-sm', 'text-foreground', 'shadow-xs', 'transition', 'hover:border-foreground/40', 'focus-visible:border-ring', 'focus-visible:outline-none', 'focus-visible:ring-4', 'focus-visible:ring-ring/15'];

    public const array FilterLabel = ['text-xs', 'font-medium', 'text-muted-foreground'];

    public const array ActiveFilter = ['inline-flex', 'items-center', 'gap-1', 'rounded-full', 'border', 'border-primary/20', 'bg-primary-soft', 'py-1', 'pl-2.5', 'pr-1', 'text-xs', 'font-medium', 'text-primary'];

    public const array FilterCount = ['inline-flex', 'min-w-5', 'items-center', 'justify-center', 'rounded-full', 'bg-primary', 'px-1.5', 'text-[11px]', 'font-semibold', 'leading-5', 'text-primary-foreground'];

    public const array CountPill = ['inline-flex', 'items-center', 'justify-center', 'rounded-full', 'bg-surface-muted', 'px-1.5', 'text-[11px]', 'font-semibold', 'leading-5', 'tabular-nums', 'text-muted-foreground', 'ring-1', 'ring-inset', 'ring-border'];

    /** Floating bulk bar: inverted pill that stays in view while rows are selected. */
    public const array BulkBar = ['sticky', 'bottom-4', 'z-30', 'mx-auto', 'my-3', 'flex', 'w-fit', 'max-w-[calc(100%-2rem)]', 'flex-wrap', 'items-center', 'gap-1', 'rounded-full', 'bg-foreground', 'py-1.5', 'pl-4', 'pr-1.5', 'text-sm', 'text-background', 'shadow-gcego-overlay', 'transition', 'duration-300', 'starting:translate-y-3', 'starting:opacity-0'];

    public const array BulkButton = ['inline-flex', 'h-8', 'items-center', 'gap-1.5', 'rounded-full', 'px-3', 'text-sm', 'font-medium', 'text-background', 'transition', 'hover:bg-background/15', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-background'];

    public const array BulkDangerButton = ['inline-flex', 'h-8', 'items-center', 'gap-1.5', 'rounded-full', 'bg-danger', 'px-3', 'text-sm', 'font-medium', 'text-danger-foreground', 'transition', 'hover:bg-danger-hover', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-background'];

    public const array EmptyState = ['flex', 'flex-col', 'items-center', 'justify-center', 'gap-1.5', 'px-6', 'py-20', 'text-center'];

    public const array EmptyIcon = ['mb-3', 'inline-flex', 'size-14', 'items-center', 'justify-center', 'rounded-2xl', 'border', 'border-border', 'bg-surface-muted', 'text-muted-foreground', 'shadow-xs', 'ring-8', 'ring-surface-muted/50'];

    public const array ErrorState = ['flex', 'flex-col', 'items-center', 'justify-center', 'gap-3', 'px-6', 'py-16', 'text-center', 'text-danger'];

    public const array Loading = ['pointer-events-none', 'absolute', 'inset-x-0', 'top-12', 'z-30', 'flex', 'justify-center'];

    public const array LoadingPill = ['inline-flex', 'items-center', 'gap-2', 'rounded-full', 'border', 'border-border', 'bg-popover', 'px-3', 'py-1.5', 'text-xs', 'font-medium', 'text-muted-foreground', 'shadow-gcego-overlay'];

    public const array Spinner = ['size-3.5', 'animate-spin', 'rounded-full', 'border-2', 'border-border', 'border-t-primary'];

    public const array ProgressLine = ['pointer-events-none', 'absolute', 'inset-x-0', 'top-0', 'z-30', 'h-0.5', 'animate-pulse', 'bg-primary'];

    public const array Notice = ['border-b', 'border-warning/30', 'bg-warning-soft', 'px-4', 'py-2.5', 'text-sm', 'text-foreground', 'sm:px-5'];

    /** Badge and Boolean variants: tone background plus text, never color alone (labels and icons carry the meaning). */
    public const array Badge = ['inline-flex', 'items-center', 'gap-1', 'whitespace-nowrap', 'rounded-full', 'px-2', 'py-0.5', 'text-xs', 'font-medium', 'ring-1', 'ring-inset'];

    /**
     * @var array<string, list<string>>
     */
    public const array Variants = [
        'default' => ['bg-primary-soft', 'text-primary', 'ring-primary/20'],
        'success' => ['bg-success-soft', 'text-success', 'ring-success/25'],
        'warning' => ['bg-warning-soft', 'text-warning', 'ring-warning/30'],
        'danger' => ['bg-danger-soft', 'text-danger', 'ring-danger/25'],
        'info' => ['bg-info-soft', 'text-info', 'ring-info/25'],
        'neutral' => ['bg-surface-muted', 'text-muted-foreground', 'ring-border'],
    ];

    /**
     * @var array<string, list<string>>
     */
    public const array IconTones = [
        'default' => ['text-primary'],
        'success' => ['text-success'],
        'warning' => ['text-warning'],
        'danger' => ['text-danger'],
        'info' => ['text-info'],
        'neutral' => ['text-muted-foreground'],
    ];

    public const array ProgressTrack = ['h-1.5', 'w-full', 'min-w-20', 'overflow-hidden', 'rounded-full', 'bg-surface-muted', 'ring-1', 'ring-inset', 'ring-border/60'];

    public const array ProgressBar = ['h-full', 'rounded-full', 'bg-primary', 'transition-[width]', 'duration-500'];

    public const array Image = ['size-10', 'rounded-gcego', 'object-cover', 'bg-surface-muted', 'ring-1', 'ring-border'];

    public const array RoundImage = ['size-10', 'rounded-full', 'object-cover', 'bg-surface-muted', 'ring-2', 'ring-card'];

    public const array Link = ['font-medium', 'text-link', 'decoration-link/30', 'underline-offset-4', 'transition', 'hover:text-link-hover', 'hover:underline', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-ring'];

    /**
     * @var array<string, string>
     */
    public const array Align = ['start' => 'text-left', 'center' => 'text-center', 'end' => 'text-right'];

    /**
     * @var array<string, string>
     */
    public const array Widths = ['xs' => 'w-16', 'sm' => 'w-24', 'md' => 'w-40', 'lg' => 'w-64', 'xl' => 'w-96', 'auto' => 'w-auto'];

    /**
     * Columns shown from a breakpoint up (header and cells).
     *
     * @var array<string, string>
     */
    public const array VisibleFrom = ['sm' => 'hidden sm:table-cell', 'md' => 'hidden md:table-cell', 'lg' => 'hidden lg:table-cell', 'xl' => 'hidden xl:table-cell'];
}
