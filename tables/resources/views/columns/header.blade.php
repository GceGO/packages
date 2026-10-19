@php
    // The identity column stays in view while scrolling sideways (see tables.table).
    $first = $frame !== null && ($frame->state->visibleColumns()[0] ?? null) === $name;
    $stickyStart = $first ? 'z-20 '.($frame->selectable ? 'left-11' : 'left-0').' '.implode(' ', $presets::StickyStartShadow) : '';
@endphp
<th scope="col" class="{{ $styles('header-cell') }} {{ $layout }} {{ $stickyStart }}" @if ($ariaSort) aria-sort="{{ $ariaSort }}" @endif data-gcego-column="{{ $name }}">
    @if ($sortable)
        <button type="button" wire:click="sortBy({{ \Illuminate\Support\Js::from($name) }}, $event.shiftKey)" class="{{ implode(' ', $presets::SortButton) }} {{ $direction !== null ? 'text-foreground' : '' }}" title="{{ __('gcego-tables::messages.sort_hint') }}">
            <span>{{ __($column->label()) }}</span>
            <span class="inline-flex transition {{ $direction === null ? 'opacity-0 group-hover/sort:opacity-60 group-focus-visible/sort:opacity-60' : 'text-primary' }}">{{ $icons->svg(match ($direction) { 'asc' => 'tables.sort-ascending', 'desc' => 'tables.sort-descending', default => 'tables.sort' }, 'size-3.5') }}</span>
            @if ($position !== null)<span class="inline-flex size-4 items-center justify-center rounded-full bg-primary text-[10px] font-semibold leading-none text-primary-foreground" aria-hidden="true">{{ $position }}</span>@endif
            <span class="sr-only">{{ match ($direction) { 'asc' => __('gcego-tables::messages.sorted_asc'), 'desc' => __('gcego-tables::messages.sorted_desc'), default => __('gcego-tables::messages.sortable') } }}</span>
        </button>
    @else
        {{ __($column->label()) }}
    @endif
</th>
