{{-- gcego-layouts::layouts.collapsible — a native <details> disclosure --}}
@php($icon = $layout->icon('size-4'))
<details id="{{ $layout->id() }}"{{ $layout->class('root', implode(' ', $presets::Collapsible)) }}@if ($open) open @endif @if ($layout->live()) wire:ignore.self @endif{{ $layout->key() }}>
    <summary{{ $layout->class('summary', implode(' ', [...$presets::Summary, ...$presets::CollapsibleSummary])) }}>
        <span class="flex min-w-0 items-center gap-2">
            @if ($icon !== null)
                <span aria-hidden="true" class="shrink-0 text-gcego-text-muted">{{ $icon }}</span>
            @endif
            <span class="min-w-0">
                <span class="block">{{ $layout->title() }}</span>
                @if ($layout->description() !== null)
                    <span{{ $layout->class('description', 'block text-xs font-normal text-gcego-text-muted') }}>{{ $layout->description() }}</span>
                @endif
            </span>
        </span>
        @include('gcego-layouts::partials.chevron')
    </summary>
    <div{{ $layout->class('body', $body) }}>{{ $layout->content() }}</div>
</details>
