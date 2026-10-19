<?php

declare(strict_types=1);

namespace Gcego\Widgets\Presets;

/**
 * Literal Tailwind classes for gcego/widgets (scanned by gcego.css through "widgets/src/Presets").
 */
final class WidgetPresets
{
    public const array Card = ['relative', 'flex', 'h-full', 'flex-col', 'gap-3', 'rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface', 'p-5', 'text-gcego-text', 'shadow-gcego-card'];

    /** Inside a WidgetHost the header leaves room for the docked range and refresh controls. */
    public const array Header = ['flex', 'min-h-8', 'items-start', 'justify-between', 'gap-3', 'group-data-[gcego-toolbar=refresh]/widget:pe-10', 'group-data-[gcego-toolbar=range]/widget:pe-48'];

    public const array Title = ['text-sm', 'font-medium', 'text-gcego-text-muted'];

    public const array SectionTitle = ['text-base', 'font-semibold', 'text-gcego-text'];

    public const array Description = ['text-xs', 'text-gcego-text-muted'];

    public const array Body = ['flex', 'min-h-0', 'flex-1', 'flex-col', 'gap-3'];

    public const array Footer = ['flex', 'items-center', 'justify-between', 'gap-2', 'border-t', 'border-gcego-border', 'pt-3', 'text-xs', 'text-gcego-text-muted'];

    public const array Value = ['text-3xl', 'font-semibold', 'tracking-tight', 'tabular-nums', 'text-gcego-text'];

    public const array Trend = ['inline-flex', 'items-center', 'gap-1', 'text-xs', 'font-medium'];

    public const array IconBadge = ['inline-flex', 'size-9', 'shrink-0', 'items-center', 'justify-center', 'rounded-gcego'];

    public const array ChartArea = ['relative', 'w-full'];

    public const array Legend = ['flex', 'flex-wrap', 'gap-3', 'text-xs', 'text-gcego-text-muted'];

    public const array EmptyState = ['flex', 'flex-1', 'flex-col', 'items-center', 'justify-center', 'gap-1', 'py-6', 'text-center', 'text-sm', 'text-gcego-text-muted'];

    public const array ErrorState = ['flex', 'flex-1', 'flex-col', 'items-center', 'justify-center', 'gap-2', 'py-6', 'text-center', 'text-sm', 'text-gcego-danger'];

    public const array Loading = ['pointer-events-none', 'absolute', 'inset-0', 'z-10', 'flex', 'items-center', 'justify-center', 'rounded-gcego-lg', 'bg-gcego-surface/70'];

    public const array Spinner = ['size-5', 'animate-spin', 'rounded-full', 'border-2', 'border-gcego-border', 'border-t-gcego-primary'];

    public const array Toolbar = ['absolute', 'end-3', 'top-3', 'z-[5]', 'flex', 'items-center', 'gap-1.5'];

    /** The custom range's date inputs, above the card while that range is chosen. */
    public const array CustomRange = ['flex', 'flex-wrap', 'items-center', 'justify-end', 'gap-2'];

    public const array Control = ['min-h-8', 'rounded-gcego', 'border', 'border-gcego-border', 'bg-gcego-surface', 'px-2.5', 'py-1', 'text-xs', 'font-medium', 'text-gcego-text', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring'];

    public const array Button = ['inline-flex', 'min-h-8', 'min-w-8', 'items-center', 'justify-center', 'gap-1.5', 'rounded-gcego', 'text-sm', 'font-medium', 'text-gcego-text-muted', 'hover:bg-gcego-surface-muted', 'hover:text-gcego-text', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring', 'disabled:opacity-50'];

    public const array Link = ['text-sm', 'font-medium', 'text-gcego-primary', 'underline-offset-2', 'hover:underline', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring'];

    public const array ProgressTrack = ['h-2.5', 'w-full', 'overflow-hidden', 'rounded-full', 'bg-gcego-surface-muted'];

    public const array DataTable = ['w-full', 'text-left', 'text-xs'];

    public const array Notice = ['rounded-gcego', 'bg-gcego-warning/10', 'px-3', 'py-2', 'text-xs', 'text-gcego-text'];

    /**
     * Tones for icons, bars and trends.
     *
     * @var array<string, array{bg: string, text: string, bar: string}>
     */
    public const array Tones = [
        'default' => ['bg' => 'bg-gcego-primary/10', 'text' => 'text-gcego-primary', 'bar' => 'bg-gcego-primary'],
        'success' => ['bg' => 'bg-gcego-success/10', 'text' => 'text-gcego-success', 'bar' => 'bg-gcego-success'],
        'warning' => ['bg' => 'bg-gcego-warning/15', 'text' => 'text-gcego-warning', 'bar' => 'bg-gcego-warning'],
        'danger' => ['bg' => 'bg-gcego-danger/10', 'text' => 'text-gcego-danger', 'bar' => 'bg-gcego-danger'],
        'info' => ['bg' => 'bg-gcego-info/10', 'text' => 'text-gcego-info', 'bar' => 'bg-gcego-info'],
        'neutral' => ['bg' => 'bg-gcego-surface-muted', 'text' => 'text-gcego-text-muted', 'bar' => 'bg-gcego-text-muted'],
    ];

    /**
     * Stats grids by column count.
     *
     * @var array<int, string>
     */
    public const array Grids = [
        1 => 'grid grid-cols-1 gap-4',
        2 => 'grid grid-cols-1 gap-4 sm:grid-cols-2',
        3 => 'grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3',
        4 => 'grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4',
    ];

    /**
     * Chart heights.
     *
     * @var array<string, string>
     */
    public const array ChartHeights = ['sm' => 'h-40', 'md' => 'h-64', 'lg' => 'h-96', 'auto' => 'h-64'];

    /**
     * Table widget heights.
     *
     * @var array<string, string>
     */
    public const array TableHeights = ['sm' => 'max-h-64', 'md' => 'max-h-96', 'lg' => 'max-h-[36rem]'];
}
