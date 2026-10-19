<?php

declare(strict_types=1);

namespace Gcego\Forms\Presets;

/**
 * Literal Tailwind classes of form styling slots and layouts. This file is
 * scanned by gcego/chroma's gcego.css (@source .../forms/src/Presets), so
 * every class here exists in production CSS. Never build class names dynamically.
 *
 * Colors are gcego/swatches semantic tokens (bg-surface, text-foreground, border-input, ring…), so forms
 * follow the theme, the accent and dark mode, and match gcego/tables controls.
 */
final class FormPresets
{
    public const array FieldRoot = ['flex', 'min-w-0', 'flex-col', 'gap-1.5'];

    public const array Label = ['text-[13px]', 'font-medium', 'leading-5', 'text-foreground/90'];

    public const array Input = ['block', 'min-h-10', 'w-full', 'min-w-0', 'rounded-gcego', 'border', 'border-border', 'bg-background', 'px-3.5', 'py-2', 'text-sm', 'text-foreground', 'shadow-[inset_0_1px_2px_rgb(0_0_0/0.05)]', 'transition', 'duration-150', 'placeholder:text-muted-foreground/70', 'hover:border-border-strong/60', 'focus-visible:border-primary', 'focus-visible:bg-surface', 'focus-visible:outline-none', 'focus-visible:ring-4', 'focus-visible:ring-primary/15', 'read-only:bg-surface-muted/50', 'disabled:cursor-not-allowed', 'disabled:opacity-60', 'aria-invalid:border-danger/70', 'aria-invalid:focus-visible:ring-danger/15'];

    /**
     * An input inside an input group (prefix / suffix / buttons): the group draws the border and the focus ring.
     */
    public const array GroupedInput = ['border-0!', 'bg-transparent!', 'shadow-none!', 'ring-0!', 'rounded-none!', 'px-3', 'focus-visible:ring-0!'];

    public const array InputGroup = ['flex', 'min-h-10', 'w-full', 'min-w-0', 'items-stretch', 'overflow-hidden', 'rounded-gcego', 'border', 'border-border', 'bg-background', 'shadow-[inset_0_1px_2px_rgb(0_0_0/0.05)]', 'transition', 'duration-150', 'hover:border-border-strong/60', 'focus-within:border-primary', 'focus-within:bg-surface', 'focus-within:ring-4', 'focus-within:ring-primary/15', 'has-aria-invalid:border-danger/70', 'has-disabled:opacity-60'];

    public const array Addon = ['flex', 'shrink-0', 'items-center', 'bg-surface-muted/60', 'px-3', 'text-sm', 'text-muted-foreground'];

    public const array AddonButton = ['flex', 'w-10', 'shrink-0', 'items-center', 'justify-center', 'text-muted-foreground', 'transition', 'hover:bg-accent', 'hover:text-foreground', 'focus-visible:bg-accent', 'focus-visible:text-foreground', 'focus-visible:outline-none', 'disabled:pointer-events-none', 'disabled:opacity-40'];

    public const array Helper = ['text-xs', 'text-muted-foreground'];

    public const array Hint = ['text-xs', 'text-muted-foreground'];

    public const array Error = ['flex', 'items-start', 'gap-1.5', 'text-sm', 'font-medium', 'text-danger'];

    public const array Required = ['text-danger'];

    public const array Choice = ['peer', 'absolute', 'inset-0', 'm-0', 'size-full', 'cursor-pointer', 'appearance-none', 'rounded-[5px]', 'border', 'border-border-strong/60', 'bg-background', 'transition', 'duration-150', 'hover:border-primary/60', 'checked:border-primary', 'checked:bg-primary', 'checked:hover:bg-primary-hover', 'focus-visible:outline-none', 'focus-visible:ring-4', 'focus-visible:ring-primary/20', 'disabled:cursor-not-allowed', 'disabled:opacity-40', 'aria-invalid:border-danger/70'];

    public const array Radio = ['peer', 'absolute', 'inset-0', 'm-0', 'size-full', 'cursor-pointer', 'appearance-none', 'rounded-full', 'border', 'border-border-strong/60', 'bg-background', 'transition', 'duration-150', 'hover:border-primary/60', 'checked:border-primary', 'checked:bg-primary', 'focus-visible:outline-none', 'focus-visible:ring-4', 'focus-visible:ring-primary/20', 'disabled:cursor-not-allowed', 'disabled:opacity-40'];

    public const array ChoiceBox = ['relative', 'mt-px', 'inline-flex', 'size-[18px]', 'shrink-0', 'items-center', 'justify-center'];

    public const array ChoiceRow = ['flex', 'items-start', 'gap-3', 'text-sm', 'text-foreground'];

    public const array ChoiceCard = ['flex', 'cursor-pointer', 'items-start', 'gap-3', 'rounded-gcego', 'border', 'border-border', 'bg-background/60', 'px-3.5', 'py-3', 'text-sm', 'text-foreground', 'transition', 'duration-150', 'hover:border-border-strong/60', 'has-checked:border-primary/50', 'has-checked:bg-primary-soft', 'has-focus-visible:ring-4', 'has-focus-visible:ring-primary/15', 'has-disabled:cursor-not-allowed', 'has-disabled:opacity-50'];

    public const array ChoiceGrid = ['grid', 'grid-cols-1', 'gap-2', 'sm:grid-cols-2'];

    public const array Toggle = ['peer', 'sr-only'];

    public const array ToggleTrack = ['relative', 'mt-px', 'inline-flex', 'h-6', 'w-11', 'shrink-0', 'items-center', 'rounded-full', 'bg-border-strong/40', 'transition-colors', 'duration-200', 'peer-checked:bg-primary', 'peer-focus-visible:ring-4', 'peer-focus-visible:ring-primary/20', 'peer-disabled:cursor-not-allowed', 'peer-disabled:opacity-50', 'after:absolute', 'after:left-0.5', 'after:size-5', 'after:rounded-full', 'after:bg-white', 'after:shadow-[0_1px_3px_rgb(0_0_0/0.25)]', "after:content-['']", 'after:transition-transform', 'after:duration-200', 'after:ease-out', 'peer-checked:after:translate-x-5'];

    public const array Section = ['flex', 'flex-col', 'rounded-2xl', 'border', 'border-border/70', 'bg-surface', 'shadow-[0_1px_2px_rgb(0_0_0/0.04),0_12px_32px_-16px_rgb(0_0_0/0.18)]'];

    public const array SectionHeader = ['flex', 'items-start', 'gap-3.5', 'border-b', 'border-border/60', 'px-6', 'py-5'];

    public const array SectionIcon = ['inline-flex', 'size-10', 'shrink-0', 'items-center', 'justify-center', 'rounded-xl', 'bg-primary-soft', 'text-primary', 'ring-1', 'ring-inset', 'ring-primary/15'];

    public const array SectionHeading = ['text-[15px]', 'font-semibold', 'leading-6', 'tracking-tight', 'text-foreground'];

    public const array SectionDescription = ['mt-0.5', 'text-[13px]', 'leading-5', 'text-pretty', 'text-muted-foreground'];

    public const array SectionBody = ['flex', 'flex-col', 'gap-5', 'px-6', 'py-6'];

    public const array Group = ['flex', 'flex-col', 'gap-5'];

    public const array RepeaterItem = ['flex', 'flex-col', 'gap-4', 'rounded-gcego-lg', 'border', 'border-border', 'bg-surface-muted/40', 'p-4', 'shadow-xs'];

    public const array RepeaterHeader = ['flex', 'items-center', 'justify-between', 'gap-2', 'border-b', 'border-border', 'pb-3'];

    public const array RepeaterIndex = ['inline-flex', 'size-6', 'items-center', 'justify-center', 'rounded-full', 'bg-primary-soft', 'text-xs', 'font-semibold', 'text-primary'];

    public const array SmallButton = ['inline-flex', 'h-9', 'items-center', 'justify-center', 'gap-2', 'rounded-gcego', 'border', 'border-border', 'bg-surface', 'px-3', 'text-sm', 'font-medium', 'text-foreground', 'shadow-xs', 'transition', 'hover:border-border-strong', 'hover:bg-accent', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-ring', 'disabled:pointer-events-none', 'disabled:opacity-50'];

    public const array PrimaryButton = ['inline-flex', 'h-9', 'items-center', 'justify-center', 'gap-2', 'rounded-gcego', 'bg-primary', 'px-4', 'text-sm', 'font-semibold', 'text-primary-foreground', 'shadow-sm', 'transition', 'hover:bg-primary-hover', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-ring', 'disabled:pointer-events-none', 'disabled:opacity-60'];

    public const array IconButton = ['inline-flex', 'size-8', 'shrink-0', 'items-center', 'justify-center', 'rounded-gcego-sm', 'text-muted-foreground', 'transition', 'hover:bg-accent', 'hover:text-foreground', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-ring', 'disabled:pointer-events-none', 'disabled:opacity-40'];

    public const array DangerIconButton = ['inline-flex', 'size-8', 'shrink-0', 'items-center', 'justify-center', 'rounded-gcego-sm', 'text-muted-foreground', 'transition', 'hover:bg-danger-soft', 'hover:text-danger', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-ring', 'disabled:pointer-events-none', 'disabled:opacity-40'];

    public const array AddButton = ['flex', 'h-11', 'w-full', 'items-center', 'justify-center', 'gap-2', 'rounded-gcego-lg', 'border', 'border-dashed', 'border-border-strong', 'text-sm', 'font-medium', 'text-muted-foreground', 'transition', 'hover:border-primary', 'hover:bg-primary-soft', 'hover:text-primary', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-ring', 'disabled:pointer-events-none', 'disabled:opacity-50'];

    public const array Tab = ['relative', '-mb-px', 'inline-flex', 'h-10', 'items-center', 'gap-2', 'border-b-2', 'border-transparent', 'px-3', 'text-sm', 'font-medium', 'whitespace-nowrap', 'text-muted-foreground', 'transition', 'hover:border-border-strong', 'hover:text-foreground', 'aria-selected:border-primary', 'aria-selected:text-foreground', 'focus-visible:rounded-t-gcego-sm', 'focus-visible:outline-2', 'focus-visible:outline-ring'];

    public const array TabList = ['flex', 'gap-2', 'overflow-x-auto', 'border-b', 'border-border', '[scrollbar-width:none]'];

    public const array Step = ['group', 'flex', 'min-w-0', 'flex-1', 'items-center', 'gap-3', 'text-sm', 'text-muted-foreground', 'aria-[current=step]:text-foreground'];

    public const array StepMarker = ['inline-flex', 'size-8', 'shrink-0', 'items-center', 'justify-center', 'rounded-full', 'border-2', 'text-xs', 'font-semibold', 'transition'];

    public const array StepConnector = ['hidden', 'h-0.5', 'min-w-6', 'flex-1', 'rounded-full', 'sm:block'];

    public const array ActionBar = ['sticky', 'bottom-4', 'z-20', 'flex', 'flex-wrap', 'items-center', 'justify-between', 'gap-3', 'rounded-2xl', 'border', 'border-border/70', 'bg-surface/85', 'px-5', 'py-3', 'shadow-[0_12px_32px_-12px_rgb(0_0_0/0.25)]', 'backdrop-blur-md'];

    public const array Summary = ['flex', 'gap-3', 'rounded-gcego-lg', 'border', 'border-danger/30', 'bg-danger-soft', 'p-4', 'text-sm', 'text-danger'];

    public const array Feedback = ['inline-flex', 'items-center', 'gap-2', 'text-sm', 'font-medium'];

    public const array Spinner = ['size-4', 'animate-spin', 'rounded-full', 'border-2', 'border-current', 'border-r-transparent', 'motion-reduce:animate-none'];

    public const array Dropzone = ['relative', 'flex', 'flex-col', 'items-center', 'justify-center', 'gap-2', 'rounded-gcego-lg', 'border', 'border-dashed', 'border-border-strong', 'bg-surface-muted/40', 'px-6', 'py-7', 'text-center', 'transition', 'hover:border-primary/70', 'hover:bg-primary-soft/50', 'has-focus-visible:border-ring', 'has-focus-visible:ring-4', 'has-focus-visible:ring-ring/15', 'has-aria-invalid:border-danger', 'has-disabled:opacity-60'];

    /**
     * @var array<int, list<string>>
     */
    public const array Grid = [
        1 => ['grid', 'grid-cols-1', 'gap-5'],
        2 => ['grid', 'grid-cols-1', 'gap-5', 'md:grid-cols-2'],
        3 => ['grid', 'grid-cols-1', 'gap-5', 'md:grid-cols-2', 'lg:grid-cols-3'],
        4 => ['grid', 'grid-cols-1', 'gap-5', 'md:grid-cols-2', 'lg:grid-cols-4'],
    ];

    /**
     * @var array<int, string>
     */
    public const array Span = [1 => '', 2 => 'md:col-span-2', 3 => 'md:col-span-2 lg:col-span-3', 4 => 'md:col-span-2 lg:col-span-4'];

    /**
     * A layout that stays in view on large screens, by its spacing from the top (LayoutBuilder::sticky()).
     *
     * @var array<int, list<string>>
     */
    public const array Sticky = [
        0 => ['lg:sticky', 'lg:top-0'],
        2 => ['lg:sticky', 'lg:top-2'],
        4 => ['lg:sticky', 'lg:top-4'],
        6 => ['lg:sticky', 'lg:top-6'],
        8 => ['lg:sticky', 'lg:top-8'],
        12 => ['lg:sticky', 'lg:top-12'],
        16 => ['lg:sticky', 'lg:top-16'],
        20 => ['lg:sticky', 'lg:top-20'],
        24 => ['lg:sticky', 'lg:top-24'],
    ];

    /**
     * A grid whose children keep their own height (LayoutBuilder::itemsStart()).
     */
    public const array ItemsStart = ['items-start'];
}
