<?php

declare(strict_types=1);

namespace Gcego\Panels\Presets;

/**
 * Default Tailwind CSS classes of panel shells. Listed here (a @source of
 * gcego/chroma) so every class ends up in the compiled stylesheet; most
 * are shared with gcego/pages and gcego/navigation to keep it small.
 * Colors are gcego/chroma theme tokens (themes and dark mode follow).
 */
final class PanelPresets
{
    public const array Document = ['min-h-dvh', 'bg-gcego-background', 'text-gcego-text', 'antialiased'];

    public const array SkipLink = ['sr-only', 'focus:not-sr-only', 'focus:fixed', 'focus:start-4', 'focus:top-4', 'focus:z-50', 'focus:rounded-gcego', 'focus:bg-gcego-surface', 'focus:px-4', 'focus:py-2'];

    /** Per layout: the shell wrapper. */
    public const array Wrapper = [
        'sidebar' => ['flex', 'min-h-dvh', 'flex-col', 'lg:flex-row'],
        'compact' => ['flex', 'min-h-dvh', 'flex-col', 'lg:flex-row'],
        'topbar' => ['flex', 'min-h-dvh', 'flex-col'],
    ];

    /**
     * Per layout: the desktop sidebar (hidden below lg: the mobile drawer replaces it).
     * It narrows to an icon rail (w-17: the links' icons sit on its center line) while its navigation is
     * compact (gcego/navigation sets data-compact). Its width animates both ways.
     */
    public const array Sidebar = [
        'sidebar' => ['group/sidebar', 'hidden', 'shrink-0', 'flex-col', 'border-e', 'border-sidebar-border', 'bg-sidebar', 'lg:sticky', 'lg:top-0', 'lg:flex', 'lg:h-dvh', 'lg:z-40', 'lg:w-72', 'lg:has-[[data-compact]]:w-17', 'lg:transition-all', 'lg:duration-300', 'lg:ease-in-out', 'motion-reduce:transition-none'],
        'compact' => ['group/sidebar', 'hidden', 'shrink-0', 'flex-col', 'border-e', 'border-sidebar-border', 'bg-sidebar', 'lg:sticky', 'lg:top-0', 'lg:flex', 'lg:h-dvh', 'lg:z-40', 'lg:w-60', 'lg:has-[[data-compact]]:w-17', 'lg:transition-all', 'lg:duration-300', 'lg:ease-in-out', 'motion-reduce:transition-none'],
    ];

    /**
     * Per layout: added to the sidebar while the user keeps it collapsed. It slides out by its own width
     * (and leaves the tab order once it has).
     */
    public const array SidebarCollapsed = [
        'sidebar' => ['lg:-ms-72', 'lg:has-[[data-compact]]:-ms-17', 'lg:invisible'],
        'compact' => ['lg:-ms-60', 'lg:has-[[data-compact]]:-ms-17', 'lg:invisible'],
    ];

    public const array SidebarHeader = ['flex', 'min-h-16', 'items-center', 'gap-3', 'border-b', 'border-sidebar-border', 'px-4.5'];

    /** The icon rail lets its links' tooltips show beside it. */
    public const array SidebarBody = ['min-h-0', 'flex-1', 'overflow-y-auto', 'overscroll-contain', 'p-3', 'group-has-[[data-compact]]/sidebar:overflow-visible'];

    public const array SidebarFooter = ['flex', 'flex-col', 'gap-2', 'border-t', 'border-sidebar-border', 'p-3'];

    /** The signed-in user at the foot of the sidebar (picture, name, email), opening the user menu. */
    public const array ProfileTrigger = ['flex', 'min-h-12', 'w-full', 'cursor-pointer', 'list-none', 'items-center', 'gap-3', 'rounded-gcego', 'px-1.5', 'py-1.5', 'text-start', 'transition-colors', 'duration-150', 'hover:bg-gcego-surface-muted', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring', 'motion-reduce:transition-none'];

    public const array ProfileText = ['min-w-0', 'flex-1', 'overflow-hidden', 'transition-opacity', 'duration-200', 'delay-100', 'motion-reduce:transition-none', 'group-has-[[data-compact]]/sidebar:w-0', 'group-has-[[data-compact]]/sidebar:flex-none', 'group-has-[[data-compact]]/sidebar:opacity-0', 'group-has-[[data-compact]]/sidebar:delay-0'];

    public const array ProfileName = ['block', 'truncate', 'text-sm', 'font-medium', 'text-gcego-text'];

    public const array ProfileEmail = ['block', 'truncate', 'text-xs', 'text-gcego-text-muted'];

    public const array ProfileChevron = ['size-4', 'text-gcego-text-muted', 'group-has-[[data-compact]]/sidebar:hidden'];

    /** The profile's menu: above it, as wide as the sidebar; beside the picture in the icon rail. */
    public const array ProfilePlacement = ['bottom-full', 'start-0', 'mb-2', 'w-full', 'group-has-[[data-compact]]/sidebar:bottom-0', 'group-has-[[data-compact]]/sidebar:start-full', 'group-has-[[data-compact]]/sidebar:mb-0', 'group-has-[[data-compact]]/sidebar:ms-3', 'group-has-[[data-compact]]/sidebar:w-56'];

    /** The user's own look in the user menu: light, dark or system, and the main color. */
    public const array Appearance = ['flex', 'flex-col', 'gap-2', 'border-y', 'border-gcego-border', 'px-3', 'py-3', 'my-1'];

    public const array AppearanceLabel = ['text-xs', 'font-medium', 'text-gcego-text-muted'];

    public const array AppearanceModes = ['grid', 'grid-cols-3', 'gap-1', 'rounded-gcego', 'bg-gcego-surface-muted', 'p-1'];

    public const array AppearanceMode = ['inline-flex', 'min-h-8', 'items-center', 'justify-center', 'gap-1.5', 'rounded-gcego-sm', 'px-2', 'text-xs', 'font-medium', 'text-gcego-text-muted', 'transition-colors', 'duration-150', 'hover:text-gcego-text', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring', 'aria-pressed:bg-gcego-surface', 'aria-pressed:text-gcego-text', 'aria-pressed:shadow-gcego-card', 'motion-reduce:transition-none'];

    public const array AccentSwatches = ['flex', 'flex-wrap', 'gap-2'];

    /** A swatch shows its own color: data-gcego-accent-swatch scopes the primary token to it. */
    public const array AccentSwatch = ['size-6', 'cursor-pointer', 'rounded-full', 'bg-gcego-primary', 'ring-offset-2', 'ring-offset-gcego-surface', 'transition-transform', 'duration-150', 'hover:scale-110', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring', 'aria-pressed:ring-2', 'aria-pressed:ring-gcego-text', 'motion-reduce:transition-none'];

    /** The user's picture, else their initials. */
    public const array Avatar = ['inline-flex', 'size-8', 'shrink-0', 'items-center', 'justify-center', 'overflow-hidden', 'rounded-full', 'bg-gcego-primary/15', 'text-xs', 'font-semibold', 'text-gcego-primary', 'ring-1', 'ring-gcego-border'];

    public const array AvatarImage = ['size-full', 'object-cover'];

    /** In the sidebar layouts the top bar's user menu is for small screens, or while the sidebar is collapsed. */
    public const array TopbarUserMenu = ['lg:hidden'];

    public const array Main = ['flex', 'min-w-0', 'flex-1', 'flex-col'];

    public const array Topbar = ['sticky', 'top-0', 'z-30', 'flex', 'min-h-16', 'items-center', 'gap-3', 'border-b', 'border-topbar-border', 'bg-topbar', 'px-4', 'sm:px-6'];

    public const array TopbarNavigation = ['hidden', 'min-w-0', 'flex-1', 'lg:block'];

    public const array TopbarEnd = ['ms-auto', 'flex', 'items-center', 'gap-2'];

    public const array Drawer = ['lg:hidden'];

    public const array Content = ['min-w-0', 'flex-1', 'focus:outline-none'];

    public const array Footer = ['border-t', 'border-gcego-border', 'px-4', 'py-4', 'text-xs', 'text-gcego-text-muted', 'sm:px-6'];

    public const array Branding = ['flex', 'min-w-0', 'items-center', 'gap-3', 'rounded-gcego', 'font-semibold', 'text-gcego-text', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring'];

    public const array Logo = ['h-8', 'w-auto', 'shrink-0'];

    public const array Monogram = ['inline-flex', 'size-8', 'shrink-0', 'items-center', 'justify-center', 'rounded-gcego', 'bg-gcego-primary', 'text-sm', 'font-semibold', 'text-gcego-primary-foreground'];

    public const array LogoLight = ['dark:hidden'];

    public const array LogoDark = ['hidden', 'dark:block'];

    /** The brand name and tagline: only the logo or monogram stays while the sidebar is an icon rail. */
    public const array BrandText = ['min-w-0', 'transition-opacity', 'duration-200', 'delay-100', 'motion-reduce:transition-none', 'group-has-[[data-compact]]/sidebar:w-0', 'group-has-[[data-compact]]/sidebar:opacity-0', 'group-has-[[data-compact]]/sidebar:delay-0'];

    /** The switcher's title and chevron, hidden while the sidebar is an icon rail. */
    public const array SwitcherText = ['min-w-0', 'truncate', 'group-has-[[data-compact]]/sidebar:sr-only'];

    public const array BrandName = ['truncate', 'text-sm'];

    public const array Tagline = ['block', 'truncate', 'text-xs', 'font-normal', 'text-gcego-text-muted'];

    public const array Toggle = ['hidden', 'size-9', 'items-center', 'justify-center', 'rounded-gcego', 'text-gcego-text-muted', 'transition-colors', 'duration-150', 'hover:bg-gcego-surface-muted', 'hover:text-gcego-text', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring', 'motion-reduce:transition-none', 'lg:inline-flex'];

    public const array Menu = ['relative'];

    public const array MenuTrigger = ['inline-flex', 'min-h-10', 'max-w-56', 'cursor-pointer', 'list-none', 'items-center', 'gap-2', 'rounded-gcego', 'px-3', 'py-2', 'text-sm', 'font-medium', 'text-gcego-text', 'hover:bg-gcego-surface-muted', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring'];

    public const array MenuPanel = ['absolute', 'z-40', 'min-w-56', 'max-w-[calc(100vw-2rem)]', 'rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface', 'p-1.5', 'shadow-gcego-overlay'];

    /** Where a menu opens: below, end-aligned (top bar), or above, start-aligned (sidebar footer). */
    public const array MenuPlacement = [
        'end' => ['end-0', 'top-full', 'mt-2'],
        'start' => ['start-0', 'bottom-full', 'mb-2'],
    ];

    public const array MenuHeader = ['px-3', 'py-2', 'text-xs', 'text-gcego-text-muted'];

    public const array MenuItem = ['flex', 'min-h-10', 'w-full', 'items-center', 'gap-3', 'rounded-gcego', 'px-3', 'py-2', 'text-start', 'text-sm', 'text-gcego-text', 'hover:bg-gcego-surface-muted', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring'];

    public const array MenuItemCurrent = ['bg-gcego-primary/10', 'text-gcego-primary'];

    public const array Icon = ['size-5', 'shrink-0'];

    public const array Empty = ['flex', 'flex-col', 'items-center', 'gap-2', 'rounded-gcego-lg', 'border', 'border-dashed', 'border-gcego-border-strong', 'bg-gcego-surface', 'px-6', 'py-12', 'text-center'];

    public const array EmptyWrapper = ['mx-auto', 'flex', 'w-full', 'min-w-0', 'flex-col', 'gap-6', 'px-4', 'py-6', 'sm:px-6', 'lg:px-8', 'lg:py-8', 'max-w-7xl'];

    public const array EmptyTitle = ['text-sm', 'font-semibold', 'text-gcego-text'];

    public const array EmptyText = ['max-w-prose', 'text-sm', 'text-gcego-text-muted'];
}
