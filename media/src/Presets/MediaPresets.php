<?php

declare(strict_types=1);

namespace Gcego\Media\Presets;

/**
 * Default Tailwind CSS classes of media views (a gcego/chroma @source).
 * Mostly classes other GCEGO packages already use, to keep the stylesheet
 * small; colors are theme tokens.
 */
final class MediaPresets
{
    public const array Dropzone = ['relative', 'flex', 'flex-col', 'items-center', 'justify-center', 'gap-2', 'rounded-gcego-lg', 'border', 'border-dashed', 'border-gcego-border-strong', 'bg-gcego-surface', 'px-6', 'py-8', 'text-center', 'transition-colors', 'motion-reduce:transition-none'];

    public const array DropzoneActive = ['border-gcego-primary', 'bg-gcego-primary/10'];

    public const array Button = ['inline-flex', 'min-h-10', 'cursor-pointer', 'items-center', 'justify-center', 'gap-2', 'rounded-gcego', 'border', 'border-gcego-border', 'bg-gcego-surface', 'px-3', 'py-2', 'text-sm', 'font-medium', 'text-gcego-text', 'hover:bg-gcego-surface-muted', 'focus-within:outline-2', 'focus-within:outline-offset-2', 'focus-within:outline-gcego-ring'];

    public const array Hint = ['text-xs', 'text-gcego-text-muted'];

    public const array Progress = ['h-1.5', 'w-full', 'overflow-hidden', 'rounded-full', 'bg-gcego-surface-muted'];

    public const array ProgressBar = ['h-full', 'bg-gcego-primary', 'motion-safe:transition-all'];

    public const array Error = ['text-sm', 'text-gcego-danger'];

    public const array FileList = ['flex', 'flex-col', 'gap-2'];

    public const array FileItem = ['flex', 'min-w-0', 'items-center', 'gap-3', 'rounded-gcego', 'border', 'border-gcego-border', 'bg-gcego-surface', 'p-2'];

    public const array Thumb = ['size-12', 'shrink-0', 'rounded-gcego', 'object-cover', 'bg-gcego-surface-muted'];

    public const array FileIcon = ['inline-flex', 'size-12', 'shrink-0', 'items-center', 'justify-center', 'rounded-gcego', 'bg-gcego-surface-muted', 'text-gcego-text-muted'];

    public const array FileName = ['min-w-0', 'flex-1', 'truncate', 'text-sm', 'text-gcego-text'];

    public const array FileMeta = ['block', 'truncate', 'text-xs', 'font-normal', 'text-gcego-text-muted'];

    public const array Remove = ['inline-flex', 'size-9', 'shrink-0', 'items-center', 'justify-center', 'rounded-gcego', 'text-gcego-text-muted', 'hover:bg-gcego-surface-muted', 'hover:text-gcego-text', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring'];

    public const array Library = ['mx-auto', 'flex', 'w-full', 'min-w-0', 'flex-col', 'gap-6', 'px-4', 'py-6', 'sm:px-6', 'lg:px-8', 'lg:py-8', 'max-w-7xl'];

    public const array Toolbar = ['flex', 'flex-wrap', 'items-end', 'gap-3'];

    public const array Field = ['flex', 'flex-col', 'gap-1', 'text-sm', 'text-gcego-text'];

    public const array Control = ['min-h-10', 'rounded-gcego', 'border', 'border-gcego-border', 'bg-gcego-surface', 'px-3', 'py-2', 'text-sm', 'text-gcego-text', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring'];

    public const array Grid = ['grid', 'grid-cols-2', 'gap-4', 'sm:grid-cols-3', 'lg:grid-cols-4', 'xl:grid-cols-5'];

    public const array Card = ['relative', 'flex', 'min-w-0', 'flex-col', 'overflow-hidden', 'rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface'];

    public const array CardSelected = ['border-gcego-primary', 'outline-2', 'outline-gcego-primary'];

    public const array CardPreview = ['flex', 'aspect-square', 'items-center', 'justify-center', 'bg-gcego-surface-muted'];

    public const array CardImage = ['size-full', 'object-cover'];

    public const array CardBody = ['flex', 'min-w-0', 'flex-col', 'gap-1', 'p-3'];

    public const array CardCheckbox = ['absolute', 'start-2', 'top-2', 'size-4', 'rounded', 'border-gcego-border-strong', 'text-gcego-primary', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring'];

    public const array Table = ['w-full', 'text-sm'];

    public const array Cell = ['px-4', 'py-3', 'text-start', 'align-middle'];

    public const array Details = ['rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface', 'p-4'];

    public const array DetailsList = ['grid', 'grid-cols-1', 'gap-x-6', 'gap-y-2', 'text-sm', 'sm:grid-cols-2'];

    public const array Term = ['text-xs', 'font-medium', 'uppercase', 'tracking-wide', 'text-gcego-text-muted'];

    public const array Link = ['text-sm', 'font-medium', 'text-gcego-primary', 'underline-offset-2', 'hover:underline', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring', 'rounded-gcego'];

    public const array Empty = ['flex', 'flex-col', 'items-center', 'gap-2', 'rounded-gcego-lg', 'border', 'border-dashed', 'border-gcego-border-strong', 'bg-gcego-surface', 'px-6', 'py-12', 'text-center'];

    public const array Preview = ['max-h-96', 'w-full', 'rounded-gcego', 'object-contain', 'bg-gcego-surface-muted'];

    public const array Title = ['text-2xl', 'font-semibold', 'tracking-tight', 'text-gcego-text'];
}
