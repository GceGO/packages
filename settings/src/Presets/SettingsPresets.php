<?php

declare(strict_types=1);

namespace Gcego\Settings\Presets;

/**
 * Default Tailwind CSS classes of settings views (a gcego/chroma @source).
 * Colors are theme tokens, so light, dark and custom themes apply; scope
 * badges always carry text (never color alone); focus is always visible;
 * motion respects prefers-reduced-motion.
 */
final class SettingsPresets
{
    public const array Wrapper = ['mx-auto', 'flex', 'w-full', 'min-w-0', 'max-w-7xl', 'flex-col', 'gap-6', 'px-4', 'py-6', 'sm:px-6', 'lg:flex-row', 'lg:items-start', 'lg:px-8', 'lg:py-8'];

    public const array Sidebar = ['flex', 'w-full', 'shrink-0', 'flex-col', 'gap-4', 'lg:sticky', 'lg:top-6', 'lg:w-72'];

    public const array Navigation = ['flex', 'flex-col', 'gap-1', 'rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface', 'p-2'];

    public const array NavigationLink = ['flex', 'min-h-10', 'items-center', 'gap-2', 'rounded-gcego', 'px-3', 'py-2', 'text-sm', 'text-gcego-text', 'hover:bg-gcego-surface-muted', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring'];

    public const array NavigationActive = ['bg-gcego-surface-muted', 'font-semibold', 'text-gcego-primary'];

    public const array Content = ['flex', 'min-w-0', 'flex-1', 'flex-col', 'gap-6'];

    public const array Header = ['flex', 'flex-col', 'gap-1'];

    public const array Title = ['text-2xl', 'font-semibold', 'tracking-tight', 'text-gcego-text'];

    public const array Description = ['max-w-prose', 'text-sm', 'text-gcego-text-muted'];

    public const array Group = ['flex', 'flex-col', 'gap-6'];

    public const array Section = ['rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface', 'p-4', 'sm:p-6'];

    public const array SectionTitle = ['text-base', 'font-semibold', 'text-gcego-text'];

    public const array SectionGrid = ['mt-4', 'grid', 'grid-cols-1', 'gap-6'];

    /** @var array<int, string> */
    public const array Columns = [1 => '', 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3', 4 => 'md:grid-cols-4'];

    /** @var array<int, string> */
    public const array Spans = [1 => '', 2 => 'md:col-span-2', 3 => 'md:col-span-3', 4 => 'md:col-span-4'];

    public const array Field = ['flex', 'min-w-0', 'flex-col', 'gap-2'];

    public const array FieldMeta = ['flex', 'flex-wrap', 'items-center', 'gap-2', 'text-xs', 'text-gcego-text-muted'];

    public const array Scope = ['inline-flex', 'items-center', 'gap-2', 'rounded-full', 'border', 'border-gcego-border', 'bg-gcego-surface', 'px-3', 'py-1', 'text-xs', 'font-medium', 'text-gcego-text'];

    public const array Badge = ['inline-flex', 'items-center', 'gap-1', 'rounded-full', 'px-2.5', 'py-0.5', 'text-xs', 'font-medium', 'ring-1', 'ring-inset'];

    public const array InheritedBadge = ['bg-gcego-surface-muted', 'text-gcego-text-muted', 'ring-gcego-border'];

    public const array OverrideBadge = ['bg-gcego-info/10', 'text-gcego-info', 'ring-gcego-info/20'];

    public const array SensitiveBadge = ['bg-gcego-warning/15', 'text-gcego-text', 'ring-gcego-warning/30'];

    public const array Actions = ['flex', 'flex-wrap', 'items-center', 'justify-end', 'gap-3', 'border-t', 'border-gcego-border', 'pt-4'];

    public const array Link = ['text-sm', 'font-medium', 'text-gcego-primary', 'underline-offset-2', 'hover:underline', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring', 'rounded-gcego'];

    public const array Control = ['min-h-10', 'w-full', 'rounded-gcego', 'border', 'border-gcego-border', 'bg-gcego-surface', 'px-3', 'py-2', 'text-sm', 'text-gcego-text', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring'];

    public const array History = ['rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface'];

    public const array HistoryList = ['divide-y', 'divide-gcego-border'];

    public const array HistoryItem = ['flex', 'min-w-0', 'flex-col', 'gap-1', 'px-4', 'py-3', 'text-sm', 'sm:flex-row', 'sm:items-start', 'sm:justify-between', 'sm:gap-4'];

    public const array Value = ['font-mono', 'text-xs', 'break-all', 'text-gcego-text-muted'];

    public const array Empty = ['flex', 'flex-col', 'items-center', 'gap-2', 'px-6', 'py-12', 'text-center', 'text-sm', 'text-gcego-text-muted'];

    public const array Loading = ['text-sm', 'text-gcego-text-muted', 'motion-safe:animate-pulse'];

    public const array Error = ['rounded-gcego', 'border', 'border-gcego-danger/30', 'bg-gcego-danger/10', 'px-4', 'py-3', 'text-sm', 'text-gcego-danger'];

    public const array Status = ['rounded-gcego', 'border', 'border-gcego-border', 'bg-gcego-surface-muted', 'px-4', 'py-3', 'text-sm', 'text-gcego-text'];

    public const array Unsaved = ['text-sm', 'font-medium', 'text-gcego-warning'];

    public const array Dialog = ['fixed', 'inset-0', 'z-50', 'flex', 'items-center', 'justify-center', 'bg-black/40', 'p-4'];

    public const array DialogPanel = ['w-full', 'max-w-md', 'rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface', 'p-6', 'shadow-gcego-overlay'];
}
