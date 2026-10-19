@php
    $state = $frame->state;
    $visible = $state->visibleColumns();
    $compact = $state->density === 'compact';
    $cellClass = $compact ? implode(' ', $presets::CompactCell) : $styles('cell');
    $label = $table->get('label');
    $description = $table->get('description');
    $headingId = $frame->id('heading');
    $pageKeys = array_values(array_filter(array_column($frame->rows, 'key')));
    $pageSelected = $frame->selection !== null && $frame->selection->containsAll($pageKeys);
    $pagePartly = ! $pageSelected && $frame->selection !== null && array_intersect($pageKeys, $frame->selection->keys) !== [];
    $columnCount = count($visible) + ($frame->selectable ? 1 : 0) + ($frame->hasRowActions ? 1 : 0);
    $loadingTargets = 'search,filters,sort,page,perPage,gotoPage,nextPage,previousPage,gotoCursor,sortBy,resetFilters,removeFilter,resetTable,loadView,refresh,setDensity';
    $sticky = implode(' ', $presets::StickyCell);
    // The identity column (the first visible one) stays in view while scrolling sideways, after the checkbox column.
    $firstColumn = $visible[0] ?? null;
    $firstOffset = $frame->selectable ? 'left-11' : 'left-0';
@endphp
<section
    class="{{ $styles('root') }}"
    data-gcego-table="{{ $table->tableName() }}"
    @if ($label) aria-labelledby="{{ $headingId }}" @else aria-label="{{ __('gcego-tables::messages.table') }}" @endif
    x-data="{
        expanded: false,
        scrolledX: false,
        atEnd: true,
        scrolledY: false,
        measure() {
            const scroller = this.$refs.scroller;
            if (! scroller) return;
            this.scrolledX = scroller.scrollLeft > 1;
            this.atEnd = scroller.scrollLeft + scroller.clientWidth >= scroller.scrollWidth - 1;
            this.scrolledY = scroller.scrollTop > 1;
        },
        native: false,
        section: null,
        init() {
            this.section = this.$el;
            this.$nextTick(() => this.measure());
            document.addEventListener('fullscreenchange', () => {
                this.native = document.fullscreenElement === this.section;
                if (! this.native && this.expanded && ! this.fallback) this.expanded = false;
                if (this.native) this.expanded = true;
                this.$nextTick(() => this.measure());
            });
            if (window.ResizeObserver && this.$refs.scroller) {
                const observer = new ResizeObserver(() => this.measure());
                observer.observe(this.$refs.scroller);
                this.$refs.scroller.querySelector('table') && observer.observe(this.$refs.scroller.querySelector('table'));
            }
        },
        fallback: false,
        toggleExpanded() {
            // Native fullscreen where available; otherwise a fixed overlay (iPhone Safari has no element fullscreen).
            if (document.fullscreenElement === this.section) { document.exitFullscreen(); return; }
            if (! this.expanded && this.section.requestFullscreen) { this.section.requestFullscreen().catch(() => this.overlay(true)); return; }
            this.overlay(! this.expanded);
        },
        overlay(on) {
            this.fallback = on;
            this.expanded = on;
            document.documentElement.classList.toggle('overflow-hidden', on);
            this.$nextTick(() => this.measure());
        },
    }"
    x-on:keydown.escape.window="fallback && overlay(false)"
    x-on:livewire:navigating.window="fallback && overlay(false)"
    x-bind:class="fallback ? 'fixed! inset-2 z-50 sm:inset-6' : (native ? 'h-dvh rounded-none! border-0!' : '')"
    x-bind:data-expanded="expanded"
>
    {{-- Fullscreen backdrop --}}
    <template x-teleport="body">
        <div x-show="fallback" x-transition.opacity x-on:click="overlay(false)" class="fixed inset-0 z-40 bg-overlay backdrop-blur-sm" aria-hidden="true" style="display: none"></div>
    </template>

    @if ($label || $description || $frame->hasHeaderActions)
        <header class="{{ $styles('header') }} sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 flex-col gap-1">
                @if ($label)
                    <h2 id="{{ $headingId }}" class="truncate text-base font-semibold tracking-tight text-foreground">{{ __($label) }}</h2>
                @endif
                @if ($description)
                    <p class="text-sm text-muted-foreground">{{ __($description) }}</p>
                @endif
            </div>
            @if ($frame->hasHeaderActions && $frame->live)
                <div class="flex flex-wrap items-center gap-2">
                    @livewire('gcego-actions', ['definition' => $frame->table->definition, 'placement' => 'header'], key($frame->id('header-actions')))
                </div>
            @endif
        </header>
    @endif

    @include('gcego-tables::tables.toolbar')

    @if ($frame->notice)
        <p role="status" aria-live="polite" class="{{ implode(' ', $presets::Notice) }}" data-gcego-table-notice>{{ $frame->notice }}</p>
    @endif

    <div class="relative flex min-h-0 flex-1 flex-col">
        {{-- Loading: a progress line and a small status pill; rows stay visible underneath. --}}
        <div wire:loading.block wire:target="{{ $loadingTargets }}" class="{{ implode(' ', $presets::ProgressLine) }}" style="display: none" aria-hidden="true"></div>
        <div wire:loading.flex wire:target="{{ $loadingTargets }}" class="{{ implode(' ', $presets::Loading) }}" role="status" style="display: none">
            <span class="{{ implode(' ', $presets::LoadingPill) }}"><span class="{{ implode(' ', $presets::Spinner) }}" aria-hidden="true"></span>{{ __('gcego-tables::messages.loading') }}</span>
        </div>

        <div
            x-ref="scroller"
            x-on:scroll.passive="measure()"
            x-bind:data-scrolled-x="scrolledX.toString()"
            x-bind:data-at-end="atEnd.toString()"
            x-bind:class="expanded ? 'max-h-none flex-1' : ''"
            data-scrolled-x="false"
            data-at-end="true"
            tabindex="0"
            aria-label="{{ __('gcego-tables::messages.scroll_region') }}"
            class="{{ implode(' ', $presets::Scroller) }} focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring"
            wire:loading.class="[&_tbody]:opacity-50 [&_tbody]:transition-opacity"
            wire:target="{{ $loadingTargets }}"
        >
            <table class="{{ $styles('table') }}" @if ($label) aria-describedby="{{ $headingId }}" @endif>
                <caption class="sr-only">{{ $label ? __($label) : __('gcego-tables::messages.table') }}@if ($frame->pagination?->total !== null), {{ trans_choice('gcego-tables::messages.records', $frame->pagination->total, ['count' => $frame->pagination->total]) }}@endif</caption>
                <thead class="{{ $styles('head') }}" x-bind:class="scrolledY ? '[&_th]:shadow-[0_1px_0_var(--gcego-border),0_10px_16px_-14px_var(--gcego-overlay)]' : ''">
                    <tr>
                        @if ($frame->selectable)
                            <th scope="col" class="{{ $styles('header-cell') }} left-0 z-20 w-11 min-w-11 max-w-11 pr-0">
                                <x-gcego-tables::checkbox :indeterminate="$pagePartly" aria-label="{{ __('gcego-tables::messages.select_page') }}" :checked="$pageSelected" :disabled="$pageKeys === []"
                                    :wire:click="$pageSelected ? 'clearSelection' : 'selectPage'" />
                            </th>
                        @endif
                        @foreach ($visible as $name)
                            @isset($headerIndexes[$name])
                                {{ $children->render($headerIndexes[$name]) }}
                            @endisset
                        @endforeach
                        @if ($frame->hasRowActions)
                            <th scope="col" class="{{ $styles('header-cell') }} right-0 z-20 w-px text-right {{ implode(' ', $presets::StickyEndShadow) }}"><span class="sr-only">{{ __('gcego-tables::messages.actions') }}</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="{{ $styles('tbody') }}">
                    @if ($frame->error)
                        <tr><td colspan="{{ $columnCount }}">@include('gcego-tables::states.error')</td></tr>
                    @elseif ($frame->isEmpty())
                        <tr><td colspan="{{ $columnCount }}">@include('gcego-tables::states.empty')</td></tr>
                    @else
                        @foreach ($frame->rows as $row)
                            <tr class="{{ $styles('row') }}" wire:key="{{ $frame->id('row-'.($row['key'] ?? $loop->index)) }}" @if ($frame->selectable) aria-selected="{{ $row['selected'] ? 'true' : 'false' }}" @endif data-gcego-row="{{ $row['key'] }}">
                                @if ($frame->selectable)
                                    <td class="{{ $cellClass }} sticky left-0 z-[2] w-11 min-w-11 max-w-11 pr-0 {{ $sticky }} group-aria-selected/row:shadow-[inset_3px_0_0_var(--gcego-primary)]">
                                        @if ($row['key'] !== null)
                                            <x-gcego-tables::checkbox wire:model.live="selected" :value="$row['key']" aria-label="{{ __('gcego-tables::messages.select_row', ['row' => $loop->iteration]) }}" />
                                        @endif
                                    </td>
                                @endif
                                @foreach ($visible as $name)
                                    @php($column = $schema->columns[$name])
                                    <td class="{{ $cellClass }} {{ $column->layoutClasses() }} @if ($name === $firstColumn) sticky {{ $firstOffset }} z-[1] {{ $sticky }} {{ implode(' ', $presets::StickyStartShadow) }} @endif" data-gcego-cell="{{ $name }}">
                                        @if ($row['cells'][$name]['masked'] ?? false)
                                            @include('gcego-tables::columns.masked')
                                        @else
                                            @include($column->cellView(), ['cell' => $row['cells'][$name], 'column' => $column])
                                        @endif
                                    </td>
                                @endforeach
                                @if ($frame->hasRowActions)
                                    <td class="{{ $cellClass }} sticky right-0 z-[1] whitespace-nowrap text-right {{ $sticky }} {{ implode(' ', $presets::StickyEndShadow) }}">
                                        <div class="inline-flex items-center justify-end gap-0.5">
                                            @foreach ($row['actions'] as $action)
                                                <button type="button"
                                                    wire:click="runAction({{ \Illuminate\Support\Js::from($action['name']) }}, {{ \Illuminate\Support\Js::from($row['key']) }})"
                                                    title="{{ $action['label'] }}"
                                                    class="{{ implode(' ', $action['icon'] !== null ? ($action['destructive'] ? $presets::DangerRowIconButton : $presets::RowIconButton) : ($action['destructive'] ? $presets::DangerRowButton : $presets::RowButton)) }}">
                                                    @if ($action['icon'] !== null)
                                                        {{ $icons->svg($action['icon'], 'size-4') }}<span class="sr-only">{{ $action['label'] }} {{ __('gcego-tables::messages.row', ['row' => $loop->parent->iteration]) }}</span>
                                                    @else
                                                        {{ $action['label'] }}<span class="sr-only"> {{ __('gcego-tables::messages.row', ['row' => $loop->parent->iteration]) }}</span>
                                                    @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    @if ($frame->selectable && $frame->selection !== null && $frame->selection->count() > 0)
        <div class="{{ implode(' ', $presets::BulkBar) }}" role="region" aria-label="{{ __('gcego-tables::messages.bulk_actions') }}" data-gcego-bulk-bar>
            <span class="mr-2 inline-flex items-center gap-2 font-medium tabular-nums" aria-live="polite">
                <span class="size-2 rounded-full bg-primary" aria-hidden="true"></span>{{ trans_choice('gcego-tables::messages.selected', $frame->selection->count(), ['count' => $frame->selection->count()]) }}
            </span>
            <span class="mx-1 hidden h-5 w-px bg-background/20 sm:block" aria-hidden="true"></span>
            @foreach ($frame->bulkActions as $action)
                <button type="button" wire:click="runBulkAction({{ \Illuminate\Support\Js::from($action['name']) }})" wire:loading.attr="disabled" class="{{ implode(' ', $action['destructive'] ? $presets::BulkDangerButton : $presets::BulkButton) }}">{{ $action['label'] }}</button>
            @endforeach
            <button type="button" wire:click="clearSelection" class="{{ implode(' ', $presets::BulkButton) }} size-8 justify-center px-0" aria-label="{{ __('gcego-tables::messages.clear_selection') }}" title="{{ __('gcego-tables::messages.clear_selection') }}">{{ $icons->svg('heroicon:x-mark', 'size-4') }}</button>
        </div>
    @endif

    @include('gcego-tables::tables.footer')

    @if ($frame->live && ($frame->hasRowActions || $frame->bulkActions !== []))
        @livewire('gcego-actions', ['definition' => $frame->table->definition, 'channel' => $frame->hostId, 'showButtons' => false], key($frame->id('row-actions')))
    @endif
</section>
