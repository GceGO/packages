<?php

declare(strict_types=1);

namespace Gcego\Notifications\Presets;

/**
 * Default Tailwind CSS classes of notification views (a gcego/chroma
 *
 * @source, so every class is compiled). Mostly classes the other GCEGO
 * packages already use, to keep the stylesheet small. Colors are theme
 * tokens (themes and dark mode follow).
 */
final class NotificationPresets
{
    public const array ToastWrapper = [
        'top-right' => ['pointer-events-none', 'fixed', 'inset-x-0', 'top-0', 'z-50', 'flex', 'flex-col', 'items-end', 'gap-2', 'p-4', 'sm:p-6'],
        'top-center' => ['pointer-events-none', 'fixed', 'inset-x-0', 'top-0', 'z-50', 'flex', 'flex-col', 'items-center', 'gap-2', 'p-4', 'sm:p-6'],
        'bottom-right' => ['pointer-events-none', 'fixed', 'inset-x-0', 'bottom-0', 'z-50', 'flex', 'flex-col', 'items-end', 'gap-2', 'p-4', 'sm:p-6'],
        'bottom-center' => ['pointer-events-none', 'fixed', 'inset-x-0', 'bottom-0', 'z-50', 'flex', 'flex-col', 'items-center', 'gap-2', 'p-4', 'sm:p-6'],
    ];

    public const array Toast = ['pointer-events-auto', 'flex', 'w-full', 'max-w-sm', 'items-start', 'gap-3', 'rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface', 'p-4', 'shadow-gcego-overlay', 'motion-safe:transition', 'motion-safe:duration-200'];

    /** Icon colors per type. */
    public const array TypeIcon = [
        'success' => ['text-gcego-success'],
        'error' => ['text-gcego-danger'],
        'warning' => ['text-gcego-warning'],
        'info' => ['text-gcego-info'],
        'neutral' => ['text-gcego-text-muted'],
    ];

    public const array ToastIcon = ['size-5', 'shrink-0'];

    public const array ToastIconBox = ['inline-flex', 'shrink-0'];

    public const array ToastText = ['min-w-0', 'flex-1'];

    public const array ToastTitle = ['text-sm', 'font-semibold', 'text-gcego-text', 'break-words'];

    public const array ToastBody = ['mt-1', 'text-sm', 'text-gcego-text-muted', 'break-words'];

    public const array ToastActions = ['mt-2', 'flex', 'flex-wrap', 'gap-3'];

    public const array Link = ['text-sm', 'font-medium', 'text-gcego-primary', 'underline-offset-2', 'hover:underline', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-gcego-ring', 'rounded-gcego'];

    public const array Dismiss = ['inline-flex', 'size-9', 'shrink-0', 'items-center', 'justify-center', 'rounded-gcego', 'text-gcego-text-muted', 'hover:bg-gcego-surface-muted', 'hover:text-gcego-text', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring'];

    public const array Bell = ['relative', 'inline-flex', 'size-10', 'items-center', 'justify-center', 'rounded-gcego', 'text-gcego-text-muted', 'hover:bg-gcego-surface-muted', 'hover:text-gcego-text', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring'];

    public const array Badge = ['absolute', 'end-0', 'top-0', 'inline-flex', 'min-w-5', 'items-center', 'justify-center', 'rounded-full', 'bg-gcego-danger', 'px-1.5', 'py-0.5', 'text-xs', 'font-semibold', 'tabular-nums', 'text-gcego-danger-foreground'];

    public const array Center = ['absolute', 'end-0', 'top-full', 'z-40', 'mt-2', 'w-96', 'max-w-[calc(100vw-2rem)]', 'rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface', 'shadow-gcego-overlay'];

    public const array CenterHeader = ['flex', 'items-center', 'justify-between', 'gap-3', 'border-b', 'border-gcego-border', 'px-4', 'py-3'];

    public const array CenterTitle = ['text-sm', 'font-semibold', 'text-gcego-text'];

    public const array CenterFooter = ['border-t', 'border-gcego-border', 'px-4', 'py-3', 'text-center'];

    public const array List = ['max-h-96', 'divide-y', 'divide-gcego-border', 'overflow-y-auto', 'overscroll-contain'];

    public const array Item = ['flex', 'min-w-0', 'items-start', 'gap-3', 'px-4', 'py-3'];

    public const array Unread = ['bg-gcego-primary/10'];

    public const array Read = [];

    public const array UnreadDot = ['mt-1.5', 'size-2', 'shrink-0', 'rounded-full', 'bg-gcego-primary'];

    public const array ItemTitle = ['text-sm', 'font-medium', 'text-gcego-text', 'break-words'];

    public const array ItemBody = ['mt-1', 'text-sm', 'text-gcego-text-muted', 'break-words'];

    public const array ItemMeta = ['mt-1', 'flex', 'flex-wrap', 'items-center', 'gap-2', 'text-xs', 'text-gcego-text-muted'];

    public const array Empty = ['flex', 'flex-col', 'items-center', 'gap-2', 'px-6', 'py-12', 'text-center'];

    public const array EmptyText = ['max-w-prose', 'text-sm', 'text-gcego-text-muted'];

    public const array Loading = ['px-4', 'py-6', 'text-center', 'text-sm', 'text-gcego-text-muted'];

    public const array Inbox = ['mx-auto', 'flex', 'w-full', 'min-w-0', 'flex-col', 'gap-6', 'px-4', 'py-6', 'sm:px-6', 'lg:px-8', 'lg:py-8', 'max-w-7xl'];

    public const array InboxCard = ['rounded-gcego-lg', 'border', 'border-gcego-border', 'bg-gcego-surface'];

    public const array Toolbar = ['flex', 'flex-wrap', 'items-end', 'gap-3'];

    public const array Field = ['flex', 'flex-col', 'gap-1', 'text-sm', 'text-gcego-text'];

    public const array Control = ['min-h-10', 'rounded-gcego', 'border', 'border-gcego-border', 'bg-gcego-surface', 'px-3', 'py-2', 'text-sm', 'text-gcego-text', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring'];

    public const array Checkbox = ['size-4', 'rounded', 'border-gcego-border-strong', 'text-gcego-primary', 'focus-visible:outline-2', 'focus-visible:outline-gcego-ring'];

    public const array Title = ['text-2xl', 'font-semibold', 'tracking-tight', 'text-gcego-text'];
}
