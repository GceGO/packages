<?php

declare(strict_types=1);

namespace Gcego\Infolists\Presets;

/**
 * Literal Tailwind classes for gcego/infolists (scanned by gcego.css through "infolists/src/Presets").
 */
final class InfolistPresets
{
    public const array Wrapper = ['flex', 'flex-col', 'overflow-hidden', 'rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface', 'text-gcego-text', 'shadow-gcego-card'];

    public const array Header = ['flex', 'flex-col', 'gap-3', 'border-b', 'border-gcego-border', 'px-4', 'py-4', 'sm:flex-row', 'sm:items-start', 'sm:justify-between', 'sm:px-6'];

    public const array Title = ['text-lg', 'font-semibold', 'text-gcego-text'];

    public const array Description = ['text-sm', 'text-gcego-text-muted'];

    public const array ActionsHeader = ['flex', 'justify-end', 'px-4', 'pt-4', 'sm:px-6'];

    public const array Body = ['flex', 'flex-col', 'gap-6', 'px-4', 'py-5', 'sm:px-6'];

    public const array Section = ['rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface'];

    public const array SectionHeader = ['flex', 'items-start', 'justify-between', 'gap-3', 'border-b', 'border-gcego-border', 'px-4', 'py-3', 'sm:px-5'];

    public const array SectionTitle = ['flex', 'items-center', 'gap-2', 'text-base', 'font-semibold', 'text-gcego-text'];

    public const array SectionBody = ['flex', 'flex-col', 'gap-5', 'px-4', 'py-4', 'sm:px-5'];

    public const array Entry = ['flex', 'min-w-0', 'flex-col', 'gap-1'];

    public const array EntryLabel = ['text-sm', 'font-medium', 'text-gcego-text-muted'];

    public const array EntryValue = ['min-w-0', 'text-sm', 'text-gcego-text', 'break-words', '[overflow-wrap:anywhere]'];

    public const array EntryDescription = ['text-xs', 'text-gcego-text-muted'];

    public const array EmptyState = ['flex', 'flex-col', 'items-center', 'justify-center', 'gap-2', 'px-6', 'py-12', 'text-center', 'text-gcego-text-muted'];

    public const array Placeholder = ['text-gcego-text-muted'];

    public const array Prose = ['flex', 'flex-col', 'gap-2', '[&_a]:text-gcego-primary', '[&_a]:underline', '[&_ul]:list-disc', '[&_ul]:pl-5', '[&_ol]:list-decimal', '[&_ol]:pl-5', '[&_h2]:text-base', '[&_h2]:font-semibold', '[&_h3]:font-semibold', '[&_code]:rounded', '[&_code]:bg-gcego-surface-muted', '[&_code]:px-1', '[&_pre]:overflow-x-auto', '[&_blockquote]:border-l-2', '[&_blockquote]:border-gcego-border', '[&_blockquote]:pl-3'];

    public const array Repeatable = ['flex', 'flex-col', 'gap-3'];

    public const array RepeatableItem = ['rounded-gcego', 'border', 'border-gcego-border', 'bg-gcego-surface-muted/40', 'p-3'];

    public const array TabList = ['flex', 'gap-1', 'overflow-x-auto', 'border-b', 'border-gcego-border'];

    public const array Tab = ['inline-flex', 'items-center', 'gap-2', 'whitespace-nowrap', 'border-b-2', 'border-transparent', 'px-3', 'py-2', 'text-sm', 'font-medium', 'text-gcego-text-muted', 'hover:text-gcego-text', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring', 'aria-selected:border-gcego-primary', 'aria-selected:text-gcego-text'];

    public const array CollapseButton = ['inline-flex', 'items-center', 'rounded-gcego', 'p-1', 'text-gcego-text-muted', 'hover:bg-gcego-surface-muted', 'hover:text-gcego-text', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring'];

    public const array CopyButton = ['inline-flex', 'shrink-0', 'rounded-gcego', 'p-1', 'text-gcego-text-muted', 'hover:bg-gcego-surface-muted', 'hover:text-gcego-text', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring'];

    public const array Link = ['font-medium', 'text-gcego-primary', 'underline-offset-2', 'hover:underline', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring'];

    public const array Notice = ['rounded-gcego', 'border', 'border-gcego-border', 'bg-gcego-surface-muted', 'px-4', 'py-3', 'text-sm', 'text-gcego-text'];

    /**
     * Entry column spans: span => grid columns => classes ("full" spans the whole grid).
     *
     * @var array<int|string, array<int, string>>
     */
    public const array Spans = [
        '2' => [2 => 'sm:col-span-2', 3 => 'sm:col-span-2', 4 => 'sm:col-span-2'],
        '3' => [3 => 'sm:col-span-2 lg:col-span-3', 4 => 'sm:col-span-2 lg:col-span-3'],
        '4' => [4 => 'sm:col-span-2 lg:col-span-4'],
        'full' => [2 => 'sm:col-span-2', 3 => 'sm:col-span-2 lg:col-span-3', 4 => 'sm:col-span-2 lg:col-span-4'],
    ];

    /**
     * Split columns: breakpoint => aside width => classes.
     *
     * @var array<string, array<string, string>>
     */
    public const array SplitWidths = [
        'md' => ['sm' => 'md:grid-cols-[minmax(0,1fr)_16rem]', 'md' => 'md:grid-cols-[minmax(0,1fr)_20rem]', 'lg' => 'md:grid-cols-[minmax(0,1fr)_24rem]'],
        'lg' => ['sm' => 'lg:grid-cols-[minmax(0,1fr)_16rem]', 'md' => 'lg:grid-cols-[minmax(0,1fr)_20rem]', 'lg' => 'lg:grid-cols-[minmax(0,1fr)_24rem]'],
    ];

    /**
     * Entry grids by column count: one column on small screens.
     *
     * @var array<int, string>
     */
    public const array Grids = [
        1 => 'grid grid-cols-1 gap-x-6 gap-y-5',
        2 => 'grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2',
        3 => 'grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3',
        4 => 'grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-4',
    ];
}
