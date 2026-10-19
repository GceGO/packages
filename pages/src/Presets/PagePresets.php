<?php

declare(strict_types=1);

namespace Gcego\Pages\Presets;

/**
 * Default Tailwind CSS classes of page views. Listed here (a @source of
 * gcego/chroma) so every class ends up in the compiled stylesheet. Colors
 * are gcego/chroma theme tokens, so pages follow the theme and dark mode.
 */
final class PagePresets
{
    /** Content widths (Page::Width). */
    public const array Widths = [
        'narrow' => 'max-w-3xl',
        'standard' => 'max-w-7xl',
        'wide' => 'max-w-screen-2xl',
        'full' => 'max-w-none',
    ];

    public const array Wrapper = ['mx-auto', 'flex', 'w-full', 'min-w-0', 'flex-col', 'gap-6', 'px-4', 'py-6', 'sm:px-6', 'lg:px-8', 'lg:py-8'];

    public const array Header = ['flex', 'min-w-0', 'flex-col', 'gap-3'];

    public const array HeaderRow = ['flex', 'min-w-0', 'flex-col', 'gap-4', 'sm:flex-row', 'sm:items-start', 'sm:justify-between'];

    public const array HeaderText = ['flex', 'min-w-0', 'flex-1', 'items-start', 'gap-3'];

    public const array IconSvg = ['size-5'];

    public const array Icon = ['inline-flex', 'size-10', 'shrink-0', 'items-center', 'justify-center', 'rounded-gcego', 'bg-gcego-primary/10', 'text-gcego-primary'];

    public const array Title = ['text-2xl', 'font-semibold', 'tracking-tight', 'break-words', 'text-gcego-text'];

    public const array Description = ['mt-1', 'max-w-prose', 'text-sm', 'text-gcego-text-muted'];

    public const array Actions = ['flex', 'w-full', 'flex-wrap', 'items-center', 'gap-2', 'sm:w-auto', 'sm:shrink-0', 'sm:justify-end'];

    public const array Body = ['flex', 'min-w-0', 'flex-col', 'gap-6'];

    public const array Empty = ['flex', 'flex-col', 'items-center', 'gap-2', 'rounded-gcego-lg', 'border', 'border-dashed', 'border-gcego-border-strong', 'bg-gcego-surface', 'px-6', 'py-12', 'text-center'];

    public const array EmptyIcon = ['size-8', 'text-gcego-text-muted'];

    public const array EmptyTitle = ['text-sm', 'font-semibold', 'text-gcego-text'];

    public const array EmptyText = ['max-w-prose', 'text-sm', 'text-gcego-text-muted'];

    public const array Loading = ['pointer-events-none', 'fixed', 'inset-x-0', 'top-0', 'z-50', 'h-1', 'bg-gcego-primary'];

    public const array Content = ['max-w-prose', 'space-y-3', 'text-sm', 'leading-6', 'text-gcego-text'];

    public const array Link = ['justify-center', 'no-underline', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring'];

    public const array LinkIcon = ['size-4', 'shrink-0'];

    public const array Document = ['min-h-dvh', 'bg-gcego-background', 'text-gcego-text', 'antialiased'];

    public const array Shell = ['flex', 'min-h-dvh', 'flex-col', 'lg:flex-row'];

    public const array Sidebar = ['shrink-0', 'border-b', 'border-gcego-border', 'bg-gcego-surface', 'lg:w-72', 'lg:border-e', 'lg:border-b-0'];

    public const array Main = ['min-w-0', 'flex-1', 'focus:outline-none'];

    public const array SkipLink = ['sr-only', 'focus:not-sr-only', 'focus:fixed', 'focus:start-4', 'focus:top-4', 'focus:z-50', 'focus:rounded-gcego', 'focus:bg-gcego-surface', 'focus:px-4', 'focus:py-2'];
}
