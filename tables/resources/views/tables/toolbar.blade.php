@php($activeFilters = count($frame->state->filters))
<div class="{{ $styles('toolbar') }}" x-data="{ filters: false }">
    <div class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center">
        @if ($frame->searchable)
            <div class="relative"
                x-on:keydown.slash.window="if (! ['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName) && ! $event.target.isContentEditable && $el.closest('[data-gcego-table-host]') === document.querySelector('[data-gcego-table-host]')) { $event.preventDefault(); $refs.search.focus() }">
                <label for="{{ $frame->id('search') }}" class="sr-only">{{ __('gcego-tables::messages.search') }}</label>
                <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-muted-foreground">{{ $icons->svg('tables.search', 'size-4') }}</span>
                <input id="{{ $frame->id('search') }}" x-ref="search" type="search" wire:model.live.debounce.{{ $frame->debounceMs }}ms="search" maxlength="{{ app(\Gcego\Tables\TablesConfig::class)->searchMaxLength }}" autocomplete="off" spellcheck="false"
                    x-on:keydown.escape="$wire.resetSearch(); $el.blur()"
                    placeholder="{{ $schema->search?->placeholder ? __($schema->search->placeholder) : __('gcego-tables::messages.search_placeholder') }}"
                    class="{{ $styles('search') }}">
                <span class="absolute inset-y-0 right-2 flex items-center gap-1">
                    <span wire:loading.flex wire:target="search" class="{{ implode(' ', $presets::Spinner) }}" style="display: none" aria-hidden="true"></span>
                    @if ($frame->state->search !== null)
                        <button type="button" wire:click="resetSearch" class="inline-flex size-6 items-center justify-center rounded-full text-muted-foreground transition hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring" aria-label="{{ __('gcego-tables::messages.clear_search') }}">{{ $icons->svg('heroicon:x-mark', 'size-3.5') }}</button>
                    @else
                        <kbd class="hidden h-6 min-w-6 items-center justify-center rounded-md border border-border bg-surface-muted px-1.5 font-sans text-[11px] font-medium text-muted-foreground sm:inline-flex" title="{{ __('gcego-tables::messages.search_shortcut') }}">/</kbd>
                    @endif
                </span>
            </div>
        @endif
        @if ($filterIndexes !== [])
            <button type="button" x-on:click="filters = ! filters" x-bind:aria-expanded="filters.toString()" aria-controls="{{ $frame->id('filters') }}" aria-expanded="false" class="{{ implode(' ', $presets::ToolButton) }} self-start sm:self-auto">
                {{ $icons->svg('tables.filter', 'size-4') }}<span>{{ __('gcego-tables::messages.filters') }}</span>
                @if ($activeFilters > 0)<span class="{{ implode(' ', $presets::FilterCount) }}">{{ $activeFilters }}<span class="sr-only"> {{ __('gcego-tables::messages.active') }}</span></span>@endif
                <span class="transition-transform duration-200" x-bind:class="filters && 'rotate-180'" aria-hidden="true">{{ $icons->svg('heroicon:chevron-down', 'size-3.5') }}</span>
            </button>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @if ($frame->poll !== null)
            <span class="inline-flex h-9 items-center gap-2 rounded-full border border-success/25 bg-success-soft px-3 text-xs font-semibold text-success" title="{{ __('gcego-tables::messages.live_hint', ['seconds' => $frame->poll]) }}" data-gcego-live>
                <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-success opacity-60 motion-reduce:animate-none"></span><span class="relative inline-flex size-2 rounded-full bg-success"></span></span>
                {{ __('gcego-tables::messages.live') }}<span class="sr-only">, {{ __('gcego-tables::messages.live_hint', ['seconds' => $frame->poll]) }}</span>
            </span>
        @endif
        @if ($frame->savedViews)
            @include('gcego-tables::tables.views')
        @endif
        @if ($frame->columnManager)
            @include('gcego-tables::tables.columns')
            <div class="{{ implode(' ', $presets::Segmented) }} hidden md:inline-flex" role="group" aria-label="{{ __('gcego-tables::messages.density') }}">
                <button type="button" wire:click="setDensity('comfortable')" aria-pressed="{{ $frame->state->density === 'comfortable' ? 'true' : 'false' }}" class="{{ implode(' ', $presets::SegmentButton) }}" title="{{ __('gcego-tables::messages.density_comfortable') }}">{{ $icons->svg('heroicon:bars-3', 'size-4') }}<span class="sr-only">{{ __('gcego-tables::messages.density_comfortable') }}</span></button>
                <button type="button" wire:click="setDensity('compact')" aria-pressed="{{ $frame->state->density === 'compact' ? 'true' : 'false' }}" class="{{ implode(' ', $presets::SegmentButton) }}" title="{{ __('gcego-tables::messages.density_compact') }}">{{ $icons->svg('heroicon:bars-4', 'size-4') }}<span class="sr-only">{{ __('gcego-tables::messages.density_compact') }}</span></button>
            </div>
        @endif
        @if ($frame->exportable)
            <button type="button" wire:click="export" wire:loading.attr="disabled" wire:target="export" class="{{ implode(' ', $presets::ToolButton) }}" title="{{ __('gcego-tables::messages.export_hint') }}">
                <span wire:loading.remove wire:target="export" class="inline-flex">{{ $icons->svg('actions.export', 'size-4') }}</span>
                <span wire:loading.flex wire:target="export" class="{{ implode(' ', $presets::Spinner) }}" style="display: none" aria-hidden="true"></span>
                <span class="hidden lg:inline">{{ __('gcego-tables::messages.export') }}</span><span class="sr-only lg:hidden">{{ __('gcego-tables::messages.export') }}</span>
            </button>
        @endif
        <button type="button" wire:click="refresh" class="{{ implode(' ', $presets::IconButton) }}" aria-label="{{ __('gcego-tables::messages.refresh') }}" title="{{ __('gcego-tables::messages.refresh') }}">
            <span wire:loading.class="animate-spin" wire:target="refresh" class="inline-flex">{{ $icons->svg('actions.refresh', 'size-4') }}</span>
        </button>
        <button type="button" x-on:click="toggleExpanded()" x-bind:aria-pressed="expanded.toString()" aria-pressed="false" class="{{ implode(' ', $presets::IconButton) }}" x-bind:title="expanded ? @js(__('gcego-tables::messages.collapse')) : @js(__('gcego-tables::messages.expand'))" title="{{ __('gcego-tables::messages.expand') }}">
            <span x-show="! expanded" class="inline-flex">{{ $icons->svg('heroicon:arrows-pointing-out', 'size-4') }}</span>
            <span x-show="expanded" class="inline-flex" style="display: none">{{ $icons->svg('heroicon:arrows-pointing-in', 'size-4') }}</span>
            <span class="sr-only" x-text="expanded ? @js(__('gcego-tables::messages.collapse')) : @js(__('gcego-tables::messages.expand'))">{{ __('gcego-tables::messages.expand') }}</span>
        </button>
    </div>

    @if ($filterIndexes !== [])
        <div id="{{ $frame->id('filters') }}" x-show="filters" x-collapse.duration.250ms x-cloak class="basis-full" role="region" aria-label="{{ __('gcego-tables::messages.filters') }}">
            <div class="{{ $styles('filters') }}">
                @foreach ($filterIndexes as $index)
                    {{ $children->render($index) }}
                @endforeach
                <div class="flex items-end justify-end gap-2 sm:col-span-full">
                    <button type="button" wire:click="resetFilters" class="{{ implode(' ', $presets::RowButton) }}" @disabled($activeFilters === 0)>{{ __('gcego-tables::messages.reset_filters') }}</button>
                    <button type="button" x-on:click="filters = false" class="{{ implode(' ', $presets::ToolButton) }}">{{ __('gcego-tables::messages.done') }}</button>
                </div>
            </div>
        </div>
    @endif

    @if ($frame->indicators !== [] || $frame->state->search !== null)
        <ul class="flex basis-full flex-wrap items-center gap-2" aria-label="{{ __('gcego-tables::messages.active_filters') }}">
            @if ($frame->state->search !== null)
                <li class="{{ implode(' ', $presets::ActiveFilter) }}">
                    {{ $icons->svg('tables.search', 'size-3') }}{{ __('gcego-tables::messages.searching', ['term' => $frame->state->search]) }}
                    <button type="button" wire:click="resetSearch" class="inline-flex size-5 items-center justify-center rounded-full transition hover:bg-primary/15" aria-label="{{ __('gcego-tables::messages.clear_search') }}">{{ $icons->svg('actions.remove', 'size-3') }}</button>
                </li>
            @endif
            @foreach ($frame->indicators as $indicator)
                <li class="{{ implode(' ', $presets::ActiveFilter) }}" data-gcego-indicator="{{ $indicator['name'] }}">
                    {{ $indicator['text'] }}
                    <button type="button" wire:click="removeFilter({{ \Illuminate\Support\Js::from($indicator['name']) }})" class="inline-flex size-5 items-center justify-center rounded-full transition hover:bg-primary/15" aria-label="{{ __('gcego-tables::messages.remove_filter', ['filter' => $indicator['text']]) }}">{{ $icons->svg('actions.remove', 'size-3') }}</button>
                </li>
            @endforeach
            @if (count($frame->indicators) + ($frame->state->search !== null ? 1 : 0) > 1)
                <li><button type="button" wire:click="resetTable" class="text-xs font-medium text-muted-foreground underline-offset-4 transition hover:text-foreground hover:underline">{{ __('gcego-tables::messages.clear_all') }}</button></li>
            @endif
        </ul>
    @endif
</div>
