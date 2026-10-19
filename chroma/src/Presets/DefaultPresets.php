<?php

declare(strict_types=1);

namespace Gcego\Chroma\Presets;

/**
 * GCEGO's built-in style recipes.
 *
 * Every class is written out in full so Tailwind's compiler finds it (this
 * directory is a @source of resources/css/gcego.css). Colors, radii and
 * shadows use the gcego-* token utilities, which switch automatically
 * between light and dark mode, so no dark: variants are needed here.
 *
 * Recipes describe appearance only; the packages that own buttons, inputs,
 * tables and navigation decide markup and behavior.
 */
final class DefaultPresets
{
    /**
     * @var array<string, list<string>>
     */
    public const array Presets = [
        'surface.page' => ['min-h-dvh', 'bg-gcego-background', 'font-gcego', 'text-gcego-text', 'antialiased'],
        'surface.card' => ['rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface', 'text-gcego-text', 'shadow-gcego-card'],
        'surface.panel' => ['rounded-gcego', 'border', 'border-gcego-border', 'bg-gcego-surface-muted', 'text-gcego-text'],

        'layout.section' => ['flex', 'flex-col', 'gap-6'],
        'layout.header' => ['flex', 'flex-wrap', 'items-start', 'justify-between', 'gap-4'],
        'layout.body' => ['flex', 'flex-col', 'gap-6'],
        'layout.stack' => ['flex', 'flex-col', 'gap-4'],
        'layout.grid' => ['grid', 'grid-cols-1', 'gap-6', 'md:grid-cols-2', 'xl:grid-cols-3'],

        'typography.title' => ['text-2xl', 'font-semibold', 'tracking-tight', 'text-balance', 'text-gcego-text'],
        'typography.heading' => ['text-lg', 'font-semibold', 'text-gcego-text'],
        'typography.description' => ['text-sm', 'text-pretty', 'text-gcego-text-muted'],
        'typography.label' => ['text-sm', 'font-medium', 'text-gcego-text'],

        'interaction.focus-ring' => ['focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring'],
        'interaction.transition' => ['motion-safe:transition-colors', 'motion-safe:duration-150'],

        'state.disabled' => ['disabled:cursor-not-allowed', 'disabled:opacity-60', 'aria-disabled:cursor-not-allowed', 'aria-disabled:opacity-60'],
        'state.loading' => ['data-loading:pointer-events-none', 'data-loading:opacity-60'],
        'state.selected' => ['aria-selected:bg-gcego-surface-muted', 'aria-selected:font-medium', 'aria-current:bg-gcego-surface-muted', 'aria-current:font-medium'],
        'state.invalid' => ['aria-invalid:border-gcego-danger', 'aria-invalid:outline-gcego-danger'],

        'tone.success' => ['text-gcego-success'],
        'tone.warning' => ['text-gcego-warning'],
        'tone.danger' => ['text-gcego-danger'],
        'tone.info' => ['text-gcego-info'],

        'control.button' => [
            'inline-flex',
            'min-h-10',
            'items-center',
            'justify-center',
            'gap-2',
            'rounded-gcego',
            'px-4',
            'text-sm',
            'font-medium',
            'focus-visible:outline-2',
            'focus-visible:outline-offset-2',
            'focus-visible:outline-gcego-ring',
            'motion-safe:transition-colors',
            'motion-safe:duration-150',
            'disabled:cursor-not-allowed',
            'disabled:opacity-60',
            'data-loading:pointer-events-none',
            'data-loading:opacity-60',
        ],
        'control.button.primary' => ['bg-gcego-primary', 'text-gcego-primary-foreground', 'hover:bg-gcego-primary-hover'],
        'control.button.secondary' => ['border', 'border-gcego-border-strong', 'bg-gcego-surface', 'text-gcego-text', 'hover:bg-gcego-surface-muted'],
        'control.button.destructive' => ['bg-gcego-danger', 'text-gcego-danger-foreground', 'hover:opacity-90'],
        'control.input' => [
            'block',
            'min-h-10',
            'w-full',
            'rounded-gcego-sm',
            'border',
            'border-gcego-border-strong',
            'bg-gcego-surface',
            'px-3',
            'text-sm',
            'text-gcego-text',
            'placeholder:text-gcego-text-muted',
            'focus-visible:outline-2',
            'focus-visible:outline-offset-2',
            'focus-visible:outline-gcego-ring',
            'disabled:cursor-not-allowed',
            'disabled:opacity-60',
            'aria-invalid:border-gcego-danger',
        ],

        'data.table' => ['w-full', 'border-collapse', 'text-left', 'text-sm', 'text-gcego-text'],
        'data.table.head' => ['border-b', 'border-gcego-border', 'bg-gcego-surface-muted'],
        'data.table.header-cell' => ['px-4', 'py-3', 'text-xs', 'font-semibold', 'uppercase', 'tracking-wide', 'text-gcego-text-muted'],
        'data.table.row' => ['border-b', 'border-gcego-border', 'last:border-b-0', 'hover:bg-gcego-surface-muted'],
        'data.table.cell' => ['px-4', 'py-3', 'align-middle'],

        'navigation.item' => [
            'flex',
            'min-h-10',
            'items-center',
            'gap-3',
            'rounded-gcego-sm',
            'px-3',
            'text-sm',
            'font-medium',
            'text-gcego-text-muted',
            'hover:bg-gcego-surface-muted',
            'hover:text-gcego-text',
            'focus-visible:outline-2',
            'focus-visible:outline-offset-2',
            'focus-visible:outline-gcego-ring',
            'aria-current:bg-gcego-surface-muted',
            'aria-current:text-gcego-text',
        ],
    ];
}
