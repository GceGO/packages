<?php

declare(strict_types=1);

namespace Gcego\Vault\Presets;

/**
 * Tailwind CSS classes of the Vault screens (a gcego/chroma @source).
 * Colors are theme tokens, so light, dark and custom themes apply; status is
 * always written out (never color alone); focus is always visible.
 */
final class VaultPresets
{
    public const array Screen = ['mx-auto', 'flex', 'w-full', 'min-w-0', 'max-w-7xl', 'flex-col', 'gap-6', 'px-4', 'py-6', 'sm:px-6', 'lg:px-8', 'lg:py-8'];

    public const array Header = ['flex', 'flex-col', 'gap-3', 'sm:flex-row', 'sm:items-end', 'sm:justify-between'];

    public const array Title = ['text-2xl', 'font-semibold', 'tracking-tight', 'text-gcego-text'];

    public const array Lead = ['mt-1', 'max-w-prose', 'text-sm', 'text-gcego-text-muted'];

    public const array Card = ['rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface', 'shadow-sm'];

    public const array CardBody = ['p-4', 'sm:p-6'];

    public const array CardTitle = ['text-base', 'font-semibold', 'text-gcego-text'];

    public const array Toolbar = ['flex', 'flex-col', 'gap-3', 'border-b', 'border-gcego-border', 'p-4', 'sm:flex-row', 'sm:items-center', 'sm:justify-between'];

    public const array Table = ['w-full', 'min-w-full', 'divide-y', 'divide-gcego-border', 'text-left', 'text-sm'];

    public const array Th = ['px-4', 'py-3', 'text-xs', 'font-medium', 'uppercase', 'tracking-wide', 'text-gcego-text-muted'];

    public const array Td = ['px-4', 'py-3', 'align-top', 'text-gcego-text'];

    public const array Row = ['hover:bg-gcego-surface-muted/60'];

    public const array Label = ['mb-1', 'block', 'text-sm', 'font-medium', 'text-gcego-text'];

    public const array Hint = ['mt-1', 'text-xs', 'text-gcego-text-muted'];

    public const array Error = ['mt-1', 'text-sm', 'text-gcego-danger'];

    public const array Badge = ['inline-flex', 'items-center', 'gap-1', 'rounded-full', 'px-2.5', 'py-0.5', 'text-xs', 'font-medium', 'ring-1', 'ring-inset', 'whitespace-nowrap'];

    /** @var array<string, list<string>> */
    public const array Tones = [
        'neutral' => ['bg-gcego-surface-muted', 'text-gcego-text-muted', 'ring-gcego-border'],
        'info' => ['bg-gcego-info/10', 'text-gcego-info', 'ring-gcego-info/20'],
        'success' => ['bg-gcego-success/10', 'text-gcego-success', 'ring-gcego-success/20'],
        'warning' => ['bg-gcego-warning/15', 'text-gcego-text', 'ring-gcego-warning/30'],
        'danger' => ['bg-gcego-danger/10', 'text-gcego-danger', 'ring-gcego-danger/20'],
        'primary' => ['bg-gcego-primary/10', 'text-gcego-primary', 'ring-gcego-primary/20'],
    ];

    public const array Notice = ['rounded-gcego', 'border', 'px-4', 'py-3', 'text-sm'];

    /** @var array<string, list<string>> */
    public const array NoticeTones = [
        'success' => ['border-gcego-success/30', 'bg-gcego-success/10', 'text-gcego-text'],
        'error' => ['border-gcego-danger/30', 'bg-gcego-danger/10', 'text-gcego-text'],
        'info' => ['border-gcego-info/30', 'bg-gcego-info/10', 'text-gcego-text'],
    ];

    public const array Empty = ['flex', 'flex-col', 'items-center', 'gap-2', 'px-6', 'py-12', 'text-center', 'text-sm', 'text-gcego-text-muted'];

    public const array Stat = ['rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface', 'p-4'];

    public const array StatValue = ['mt-1', 'text-2xl', 'font-semibold', 'tabular-nums', 'text-gcego-text'];

    public const array StatLabel = ['text-xs', 'font-medium', 'uppercase', 'tracking-wide', 'text-gcego-text-muted'];

    public const array Link = ['font-medium', 'text-gcego-primary', 'underline-offset-4', 'hover:underline', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring', 'rounded-sm'];

    public const array Checkbox = ['size-4', 'rounded', 'border-gcego-border', 'text-gcego-primary', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring'];

    public const array MatrixCell = ['px-2', 'py-2', 'text-center'];

    public const array Sticky = ['sticky', 'left-0', 'z-10', 'bg-gcego-surface'];

    public const array Changed = ['bg-gcego-warning/15'];

    public const array Tabs = ['flex', 'gap-2', 'overflow-x-auto', 'border-b', 'border-gcego-border'];

    public const array Tab = ['-mb-px', 'whitespace-nowrap', 'border-b-2', 'px-3', 'py-2', 'text-sm', 'font-medium', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring'];

    public const array TabActive = ['border-gcego-primary', 'text-gcego-primary'];

    public const array TabIdle = ['border-transparent', 'text-gcego-text-muted', 'hover:text-gcego-text'];

    public const array Dialog = ['fixed', 'inset-0', 'z-50', 'flex', 'items-end', 'justify-center', 'bg-black/40', 'p-4', 'sm:items-center'];

    public const array DialogPanel = ['w-full', 'max-w-lg', 'rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface', 'p-6', 'shadow-xl'];

    public const array Grid = ['grid', 'grid-cols-1', 'gap-4', 'md:grid-cols-2'];

    public const array Pre = ['overflow-x-auto', 'rounded-gcego', 'bg-gcego-surface-muted', 'p-3', 'font-mono', 'text-xs', 'text-gcego-text'];
}
