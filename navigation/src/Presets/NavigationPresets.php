<?php

declare(strict_types=1);

namespace Gcego\Navigation\Presets;

/**
 * Literal Tailwind classes for gcego/navigation (scanned by gcego.css through "navigation/src/Presets").
 */
final class NavigationPresets
{
    /**
     * The sidebar root. Its compact (icon rail) look is pure CSS: every group-data-[compact]/nav
     * variant below applies while the root carries data-compact, so the rail paints before Alpine starts.
     */
    public const array Sidebar = ['group/nav', 'flex', 'min-w-0', 'flex-col', 'gap-1', 'text-sm'];

    public const array List = ['flex', 'min-w-0', 'flex-col', 'gap-0.5'];

    public const array Nested = ['mt-0.5', 'ms-4', 'border-s', 'border-gcego-border', 'ps-2', 'group-data-[compact]/nav:ms-0', 'group-data-[compact]/nav:border-s-0', 'group-data-[compact]/nav:ps-0'];

    public const array Item = ['group/item', 'relative', 'flex', 'min-h-10', 'w-full', 'min-w-0', 'items-center', 'gap-3', 'rounded-gcego', 'px-3', 'py-2', 'text-sm', 'font-medium', 'transition-colors', 'duration-150', 'motion-reduce:transition-none', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring'];

    public const array ItemActive = ['bg-gcego-primary/10', 'text-gcego-primary'];

    public const array ItemInactive = ['text-gcego-text-muted', 'hover:bg-gcego-surface-muted', 'hover:text-gcego-text'];

    public const array Icon = ['size-5', 'shrink-0'];

    public const array IconActive = ['text-gcego-primary'];

    public const array IconInactive = ['text-gcego-text-muted', 'group-hover/item:text-gcego-text'];

    /** Labels fade out as the sidebar narrows, and back in once it has widened (they stay the links' accessible names). */
    public const array Label = ['min-w-0', 'flex-1', 'truncate', 'text-start', 'transition-opacity', 'duration-200', 'delay-100', 'motion-reduce:transition-none', 'group-data-[compact]/nav:opacity-0', 'group-data-[compact]/nav:delay-0'];

    public const array Description = ['block', 'truncate', 'text-xs', 'font-normal', 'text-gcego-text-muted'];

    /**
     * In the icon rail a group header or heading becomes a thin divider: no empty rows between the icons.
     */
    public const array CompactDivider = ['group-data-[compact]/nav:pointer-events-none', 'group-data-[compact]/nav:mx-auto', 'group-data-[compact]/nav:my-2', 'group-data-[compact]/nav:h-px', 'group-data-[compact]/nav:min-h-0', 'group-data-[compact]/nav:w-5', 'group-data-[compact]/nav:overflow-hidden', 'group-data-[compact]/nav:p-0', 'group-data-[compact]/nav:bg-gcego-border', 'group-data-[compact]/nav:text-transparent'];

    public const array GroupHeader = ['flex', 'min-h-9', 'w-full', 'items-center', 'gap-3', 'rounded-gcego', 'px-3', 'py-1.5', 'text-xs', 'font-semibold', 'uppercase', 'tracking-wide', 'text-gcego-text-muted', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring', ...self::CompactDivider];

    public const array GroupToggle = ['cursor-pointer', 'transition-colors', 'duration-150', 'hover:text-gcego-text', 'motion-reduce:transition-none'];

    public const array GroupLabel = ['min-w-0', 'flex-1', 'truncate', 'text-start'];

    public const array Chevron = ['ms-auto', 'size-4', 'shrink-0', 'transition-transform', 'duration-200', 'motion-reduce:transition-none', 'group-data-[compact]/nav:hidden'];

    public const array ChevronOpen = ['rotate-180'];

    public const array Heading = ['px-3', 'pt-4', 'pb-1', 'text-xs', 'font-semibold', 'uppercase', 'tracking-wide', 'text-gcego-text-muted', ...self::CompactDivider];

    public const array Divider = ['my-2', 'border-t', 'border-gcego-border', 'group-data-[compact]/nav:mx-auto', 'group-data-[compact]/nav:w-5'];

    /** A badge at the end of a link without an icon. */
    public const array Badge = ['ms-auto', 'inline-flex', 'min-w-5', 'shrink-0', 'items-center', 'justify-center', 'gap-1', 'rounded-full', 'px-1.5', 'py-0.5', 'text-xs', 'font-semibold', 'tabular-nums'];

    /**
     * A badge of a link with an icon: a small pill floating over the icon's top-left corner (wider counts grow over the icon, not the label), in the
     * expanded sidebar and the icon rail alike, so it never moves when the sidebar is toggled.
     */
    public const array IconBadge = ['pointer-events-none', 'absolute', 'top-0.5', 'start-1', 'inline-flex', 'h-4', 'min-w-4', 'items-center', 'justify-center', 'gap-1', 'rounded-full', 'px-1', 'text-xs', 'leading-none', 'font-semibold', 'tabular-nums', 'ring-2', 'ring-gcego-surface'];

    /**
     * Badge variants over an icon: solid, so the number stays readable on top of the icon
     * (a default badge turns primary, to stand out from it).
     *
     * @var array<string, string>
     */
    public const array IconBadgeVariants = [
        'default' => 'bg-gcego-primary text-gcego-primary-foreground',
        'primary' => 'bg-gcego-primary text-gcego-primary-foreground',
        'success' => 'bg-gcego-success text-gcego-background',
        'warning' => 'bg-gcego-warning text-gcego-background',
        'danger' => 'bg-gcego-danger text-gcego-danger-foreground',
        'info' => 'bg-gcego-info text-gcego-background',
    ];

    /**
     * Badge variants (tone and an accessible meaning in the text, never color alone).
     *
     * @var array<string, string>
     */
    public const array BadgeVariants = [
        'default' => 'bg-gcego-surface-muted text-gcego-text',
        'primary' => 'bg-gcego-primary text-gcego-primary-foreground',
        'success' => 'bg-gcego-success/15 text-gcego-success',
        'warning' => 'bg-gcego-warning/15 text-gcego-warning',
        'danger' => 'bg-gcego-danger text-gcego-danger-foreground',
        'info' => 'bg-gcego-info/15 text-gcego-info',
    ];

    public const array Compact = ['sr-only'];

    /** The compact toggle's icon points the other way while compact. */
    public const array CompactToggleIcon = ['inline-flex', 'transition-transform', 'duration-300', 'ease-in-out', 'motion-reduce:transition-none', 'group-data-[compact]/nav:rotate-180'];

    /** Added to <html> by the mobile drawer while it is open (scroll lock). */
    public const array ScrollLock = ['overflow-hidden'];

    public const array CompactToggle = ['inline-flex', 'size-9', 'items-center', 'justify-center', 'rounded-gcego', 'text-gcego-text-muted', 'transition-colors', 'duration-150', 'hover:bg-gcego-surface-muted', 'hover:text-gcego-text', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring', 'motion-reduce:transition-none'];

    /** The row above the links: the search field and the compact toggle (stacked in the icon rail). */
    public const array Toolbar = ['flex', 'items-center', 'justify-end', 'gap-2', 'group-data-[compact]/nav:flex-col'];

    /** The search field: hidden in the icon rail, where a search button opens the sidebar and focuses it. */
    public const array Search = ['relative', 'min-w-0', 'flex-1', 'group-data-[compact]/nav:hidden'];

    public const array SearchIcon = ['pointer-events-none', 'absolute', 'start-2.5', 'top-1/2', 'size-4', '-translate-y-1/2', 'text-gcego-text-muted'];

    public const array SearchInput = ['h-9', 'w-full', 'rounded-gcego', 'border', 'border-gcego-border', 'bg-gcego-background', 'ps-8', 'pe-8', 'text-sm', 'text-gcego-text', 'transition-colors', 'duration-150', 'placeholder:text-gcego-text-muted', 'hover:border-gcego-border-strong', 'focus:border-gcego-primary', 'focus:outline-none', 'focus-visible:outline-2', 'focus-visible:outline-offset-0', 'focus-visible:outline-gcego-ring', 'motion-reduce:transition-none'];

    /** The "/" shortcut hint, replaced by a clear button while searching. */
    public const array SearchHint = ['pointer-events-none', 'absolute', 'end-2', 'top-1/2', '-translate-y-1/2', 'rounded', 'border', 'border-gcego-border', 'px-1.5', 'font-sans', 'text-xs', 'text-gcego-text-muted'];

    public const array SearchClear = ['absolute', 'end-1.5', 'top-1/2', 'inline-flex', 'size-6', '-translate-y-1/2', 'items-center', 'justify-center', 'rounded-gcego', 'text-gcego-text-muted', 'hover:bg-gcego-surface-muted', 'hover:text-gcego-text', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring'];

    public const array SearchButton = ['hidden', 'size-9', 'items-center', 'justify-center', 'rounded-gcego', 'text-gcego-text-muted', 'transition-colors', 'duration-150', 'hover:bg-gcego-surface-muted', 'hover:text-gcego-text', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring', 'motion-reduce:transition-none', 'group-data-[compact]/nav:inline-flex'];

    public const array SearchEmpty = ['px-3', 'py-6', 'text-center', 'text-sm', 'text-gcego-text-muted'];

    /**
     * The icon rail's tooltip: the link's name (and badge) beside its icon. Alpine shows it
     * (compactState(): a short delay on the first hover, then instant while moving along the rail,
     * at once on keyboard focus). aria-hidden: the link's own label stays its accessible name.
     */
    public const array Tooltip = ['pointer-events-none', 'absolute', 'start-full', 'top-1/2', 'z-50', 'ms-5', 'flex', '-translate-y-1/2', 'items-center', 'gap-2', 'rounded-gcego', 'bg-gcego-text', 'px-2.5', 'py-1.5', 'text-xs', 'font-medium', 'whitespace-nowrap', 'text-gcego-background', 'shadow-gcego-overlay'];

    public const array TooltipBadge = ['rounded-full', 'bg-gcego-background/20', 'px-1.5', 'tabular-nums'];

    /** x-transition classes of the tooltip. */
    public const array TooltipEnter = ['transition', 'duration-150', 'ease-out', 'motion-reduce:transition-none'];

    public const array TooltipLeave = ['transition', 'duration-100', 'ease-in', 'motion-reduce:transition-none'];

    public const array TooltipHidden = ['opacity-0', 'scale-95'];

    public const array TooltipShown = ['opacity-100', 'scale-100'];

    public const array Topbar = ['min-w-0'];

    public const array TopbarList = ['flex', 'min-w-0', 'flex-wrap', 'items-center', 'gap-1'];

    public const array TopbarItem = ['relative', 'inline-flex', 'min-h-10', 'items-center', 'gap-2', 'rounded-gcego', 'px-3', 'py-2', 'text-sm', 'font-medium', 'whitespace-nowrap', 'transition-colors', 'motion-reduce:transition-none', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring'];

    public const array Dropdown = ['absolute', 'top-full', 'z-30', 'mt-2', 'min-w-56', 'max-w-[calc(100vw-2rem)]', 'rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface', 'p-1.5', 'shadow-gcego-overlay'];

    /** @var array<string, string> */
    public const array DropdownAlign = ['start' => 'start-0', 'end' => 'end-0'];

    public const array MobileTrigger = ['inline-flex', 'size-11', 'items-center', 'justify-center', 'rounded-gcego', 'text-gcego-text', 'hover:bg-gcego-surface-muted', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring'];

    public const array MobileDialog = ['fixed', 'inset-0', 'z-40'];

    public const array MobileBackdrop = ['absolute', 'inset-0', 'bg-gcego-text/40', 'motion-safe:transition-opacity'];

    public const array MobilePanel = ['relative', 'flex', 'h-full', 'w-80', 'max-w-[85vw]', 'flex-col', 'gap-2', 'overflow-y-auto', 'overscroll-contain', 'bg-gcego-surface', 'p-3', 'shadow-gcego-overlay', 'focus:outline-none'];

    public const array MobileClose = ['inline-flex', 'size-11', 'items-center', 'justify-center', 'self-end', 'rounded-gcego', 'text-gcego-text-muted', 'hover:bg-gcego-surface-muted', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring'];

    public const array Breadcrumbs = ['min-w-0'];

    public const array BreadcrumbList = ['flex', 'min-w-0', 'flex-wrap', 'items-center', 'gap-1.5', 'text-sm', 'text-gcego-text-muted'];

    public const array BreadcrumbItem = ['inline-flex', 'min-w-0', 'items-center', 'gap-1.5'];

    public const array BreadcrumbLink = ['truncate', 'rounded-gcego', 'hover:text-gcego-text', 'hover:underline', 'underline-offset-2', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring'];

    public const array BreadcrumbCurrent = ['truncate', 'font-medium', 'text-gcego-text'];

    public const array Separator = ['size-4', 'shrink-0', 'text-gcego-text-muted/70', 'rtl:rotate-180'];
}
