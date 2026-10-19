<?php

declare(strict_types=1);

namespace Gcego\Workflows\Presets;

/**
 * Default Tailwind CSS classes of workflow views (a gcego/chroma @source).
 * Every class is one the other GCEGO packages already compile, so the
 * shared stylesheet does not grow; buttons use the "control.button"
 * presets of gcego/chroma. Colors are theme tokens; state colors always
 * come with a text label (never color alone).
 */
final class WorkflowPresets
{
    public const array Page = ['mx-auto', 'flex', 'w-full', 'min-w-0', 'flex-col', 'gap-6', 'px-4', 'py-6', 'sm:px-6', 'lg:px-8', 'lg:py-8', 'max-w-7xl'];

    public const array Card = ['rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface'];

    public const array CardHeader = ['flex', 'items-center', 'justify-between', 'gap-3', 'border-b', 'border-gcego-border', 'px-4', 'py-3'];

    public const array CardTitle = ['text-sm', 'font-semibold', 'text-gcego-text'];

    public const array Title = ['text-2xl', 'font-semibold', 'tracking-tight', 'text-gcego-text'];

    public const array Toolbar = ['flex', 'flex-wrap', 'items-end', 'gap-3'];

    public const array Field = ['flex', 'flex-col', 'gap-1', 'text-sm', 'text-gcego-text'];

    public const array Control = ['min-h-10', 'rounded-gcego', 'border', 'border-gcego-border', 'bg-gcego-surface', 'px-3', 'py-2', 'text-sm', 'text-gcego-text', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring'];

    public const array List = ['divide-y', 'divide-gcego-border'];

    public const array Item = ['flex', 'min-w-0', 'items-start', 'gap-3', 'px-4', 'py-3'];

    public const array ItemText = ['min-w-0', 'flex-1'];

    public const array ItemTitle = ['text-sm', 'font-medium', 'text-gcego-text', 'break-words'];

    public const array ItemBody = ['mt-1', 'text-sm', 'text-gcego-text-muted', 'break-words'];

    public const array ItemMeta = ['mt-1', 'flex', 'flex-wrap', 'items-center', 'gap-2', 'text-xs', 'text-gcego-text-muted'];

    public const array Actions = ['flex', 'flex-wrap', 'items-center', 'gap-3'];

    public const array Link = ['text-sm', 'font-medium', 'text-gcego-primary', 'underline-offset-2', 'hover:underline', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring', 'rounded-gcego'];

    public const array Badge = ['inline-flex', 'items-center', 'gap-1', 'rounded-full', 'px-2.5', 'py-0.5', 'text-xs', 'font-medium', 'ring-1', 'ring-inset'];

    /**
     * @var array<string, list<string>>
     */
    public const array Tones = [
        'info' => ['bg-gcego-info/10', 'text-gcego-info', 'ring-gcego-info/20'],
        'success' => ['bg-gcego-success/10', 'text-gcego-success', 'ring-gcego-success/20'],
        'warning' => ['bg-gcego-warning/15', 'text-gcego-text', 'ring-gcego-warning/30'],
        'danger' => ['bg-gcego-danger/10', 'text-gcego-danger', 'ring-gcego-danger/20'],
        'neutral' => ['bg-gcego-surface-muted', 'text-gcego-text-muted', 'ring-gcego-border'],
    ];

    public const array Notice = ['border-b', 'border-gcego-border', 'bg-gcego-warning/10', 'px-4', 'py-2', 'text-sm', 'text-gcego-text', 'sm:px-6'];

    public const array Error = ['text-sm', 'text-gcego-danger'];

    public const array Empty = ['flex', 'flex-col', 'items-center', 'gap-2', 'px-6', 'py-12', 'text-center'];

    public const array EmptyText = ['max-w-prose', 'text-sm', 'text-gcego-text-muted'];

    public const array Dot = ['mt-1.5', 'size-2', 'shrink-0', 'rounded-full', 'bg-gcego-primary'];
}
