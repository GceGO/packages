<?php

declare(strict_types=1);

namespace Gcego\Analytics\Presets;

/**
 * Default Tailwind CSS classes of analytics views (a gcego/chroma
 *
 * @source). Every class is one gcego/widgets or gcego/tables already
 * compiles (copied verbatim), so the shared stylesheet does not grow.
 * Colors are theme tokens; trends always pair color with text.
 */
final class AnalyticsPresets
{
    public const array Wrapper = ['mx-auto', 'flex', 'w-full', 'min-w-0', 'flex-col', 'gap-6', 'px-4', 'py-6', 'sm:px-6', 'lg:px-8', 'lg:py-8', 'max-w-7xl'];

    public const array Header = ['flex', 'flex-col', 'gap-1', 'border-b', 'border-gcego-border', 'px-4', 'py-4', 'sm:px-6'];

    public const array Title = ['text-2xl', 'font-semibold', 'tracking-tight', 'text-gcego-text'];

    public const array Description = ['text-xs', 'text-gcego-text-muted'];

    public const array Toolbar = ['flex', 'flex-wrap', 'items-center', 'justify-end', 'gap-2'];

    public const array Filters = ['grid', 'grid-cols-1', 'gap-3', 'border-b', 'border-gcego-border', 'bg-gcego-surface-muted', 'px-4', 'py-4', 'sm:grid-cols-2', 'lg:grid-cols-4', 'sm:px-6'];

    public const array FilterLabel = ['text-sm', 'font-medium', 'text-gcego-text'];

    public const array Control = ['min-h-9', 'rounded-gcego', 'border', 'border-gcego-border-strong', 'bg-gcego-surface', 'px-2.5', 'py-1.5', 'text-sm', 'text-gcego-text', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring'];

    public const array Button = ['inline-flex', 'min-h-9', 'items-center', 'gap-1.5', 'rounded-gcego', 'border', 'border-gcego-border-strong', 'bg-gcego-surface', 'px-3', 'text-sm', 'font-medium', 'text-gcego-text', 'hover:bg-gcego-surface-muted', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring', 'disabled:opacity-50'];

    public const array Panel = ['absolute', 'right-0', 'z-20', 'mt-2', 'w-72', 'rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface', 'p-3', 'shadow-gcego-card'];

    public const array Grid = ['grid', 'grid-cols-1', 'gap-4', 'sm:grid-cols-2', 'lg:grid-cols-4'];

    public const array Metric = ['relative', 'flex', 'h-full', 'flex-col', 'gap-3', 'rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface', 'p-5', 'text-gcego-text', 'shadow-gcego-card'];

    public const array MetricLabel = ['text-sm', 'font-medium', 'text-gcego-text-muted'];

    public const array MetricValue = ['text-3xl', 'font-semibold', 'tracking-tight', 'tabular-nums', 'text-gcego-text'];

    public const array MetricTrend = ['inline-flex', 'items-center', 'gap-1', 'text-xs', 'font-medium'];

    /**
     * @var array<string, string>
     */
    public const array Trends = ['up' => 'text-gcego-success', 'down' => 'text-gcego-danger', 'flat' => 'text-gcego-text-muted', 'unknown' => 'text-gcego-text-muted'];

    public const array Chart = ['relative', 'flex', 'h-full', 'flex-col', 'gap-3', 'rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface', 'p-5', 'text-gcego-text', 'shadow-gcego-card'];

    public const array ChartTitle = ['text-base', 'font-semibold', 'text-gcego-text'];

    public const array ChartArea = ['relative', 'w-full', 'h-64'];

    public const array ChartLegend = ['flex', 'flex-wrap', 'gap-3', 'text-xs', 'text-gcego-text-muted'];

    public const array DataTable = ['w-full', 'text-left', 'text-xs'];

    public const array Report = ['flex', 'flex-col', 'overflow-hidden', 'rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface', 'shadow-gcego-card', 'text-gcego-text'];

    public const array Scroller = ['relative', 'overflow-x-auto'];

    public const array ReportTable = ['w-full', 'min-w-full', 'border-collapse', 'text-left', 'text-sm'];

    public const array TableHead = ['bg-gcego-surface-muted', 'text-xs', 'font-semibold', 'uppercase', 'tracking-wide', 'text-gcego-text-muted'];

    public const array HeaderCell = ['px-4', 'py-3', 'font-semibold', 'whitespace-nowrap', 'border-b', 'border-gcego-border'];

    public const array TableBody = ['divide-y', 'divide-gcego-border'];

    public const array Row = ['transition-colors', 'hover:bg-gcego-surface-muted', 'aria-selected:bg-gcego-primary/5'];

    public const array Cell = ['px-4', 'py-3', 'align-middle', 'text-gcego-text'];

    public const array SortButton = ['group', 'inline-flex', 'items-center', 'gap-1', 'rounded-gcego', 'uppercase', 'tracking-wide', 'hover:text-gcego-text', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring'];

    public const array Footer = ['flex', 'flex-col', 'gap-3', 'border-t', 'border-gcego-border', 'px-4', 'py-3', 'text-sm', 'text-gcego-text-muted', 'sm:flex-row', 'sm:items-center', 'sm:justify-between', 'sm:px-6'];

    public const array Pagination = ['flex', 'flex-wrap', 'items-center', 'gap-1'];

    public const array PageButton = ['inline-flex', 'min-h-9', 'min-w-9', 'items-center', 'justify-center', 'rounded-gcego', 'border', 'border-gcego-border', 'bg-gcego-surface', 'px-3', 'text-sm', 'font-medium', 'text-gcego-text', 'hover:bg-gcego-surface-muted', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring', 'disabled:pointer-events-none', 'disabled:opacity-50', 'aria-[current=page]:border-gcego-primary', 'aria-[current=page]:bg-gcego-primary', 'aria-[current=page]:text-gcego-primary-foreground'];

    public const array Link = ['text-sm', 'font-medium', 'text-gcego-primary', 'underline-offset-2', 'hover:underline', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring'];

    public const array Empty = ['flex', 'flex-1', 'flex-col', 'items-center', 'justify-center', 'gap-1', 'py-6', 'text-center', 'text-sm', 'text-gcego-text-muted'];

    public const array Error = ['flex', 'flex-1', 'flex-col', 'items-center', 'justify-center', 'gap-2', 'py-6', 'text-center', 'text-sm', 'text-gcego-danger'];

    public const array Loading = ['pointer-events-none', 'absolute', 'inset-0', 'z-10', 'flex', 'items-center', 'justify-center', 'rounded-gcego-lg', 'bg-gcego-surface/70'];

    public const array Spinner = ['size-5', 'animate-spin', 'rounded-full', 'border-2', 'border-gcego-border', 'border-t-gcego-primary'];

    public const array Notice = ['rounded-gcego', 'bg-gcego-warning/10', 'px-3', 'py-2', 'text-xs', 'text-gcego-text'];
}
