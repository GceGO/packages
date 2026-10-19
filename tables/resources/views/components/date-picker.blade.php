{{--
    <x-gcego-tables::date-picker id="…" binding="filters.starts_on" :value="$state" />
    <x-gcego-tables::date-picker id="…" binding="filters.period" :value="$state" range />

    A calendar popover in place of <input type="date">: month grid (arrows, PageUp/PageDown, Home/End, Esc),
    presets, range selection with a highlighted span. Values stay "YYYY-MM-DD" (gcegoDatePicker in gcego/chroma resources/js/controls.js).
--}}
@props(['id', 'binding', 'value' => null, 'range' => false, 'labelledby' => null, 'placeholder' => null])
@php
    $config = [
        'binding' => $binding,
        'value' => $value,
        'range' => (bool) $range,
        'locale' => str_replace('_', '-', app()->getLocale()),
        'labels' => [
            'placeholder' => $placeholder ?? ($range ? __('gcego-tables::messages.any_date_range') : __('gcego-tables::messages.any_date')),
            'since' => __('gcego-tables::messages.since_date'),
            'until' => __('gcego-tables::messages.until_date'),
        ],
    ];
    $presets = $range
        ? ['today' => 'preset_today', 'yesterday' => 'preset_yesterday', 'last7' => 'preset_last7', 'last30' => 'preset_last30', 'thisMonth' => 'preset_this_month', 'lastMonth' => 'preset_last_month', 'thisYear' => 'preset_this_year']
        : ['today' => 'preset_today', 'yesterday' => 'preset_yesterday'];
@endphp
<div class="relative" wire:ignore.self x-data="gcegoDatePicker(@js($config))" x-on:click.outside="hide(false)" x-on:keydown.escape.stop="open && hide(true)">
    <div class="relative">
        <button type="button" id="{{ $id }}" x-ref="trigger" x-on:click="open ? hide(false) : show()" aria-haspopup="dialog" aria-expanded="false" x-bind:aria-expanded="open.toString()" aria-controls="{{ $id }}-calendar"
            @if ($labelledby) aria-labelledby="{{ $labelledby }} {{ $id }}" @endif
            class="group/trigger flex h-10 w-full min-w-0 items-center gap-2 rounded-gcego border border-input bg-surface pl-3 pr-9 text-left text-sm text-foreground shadow-xs transition hover:border-foreground/40 focus-visible:border-ring focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-ring/15 aria-expanded:border-ring aria-expanded:ring-4 aria-expanded:ring-ring/15">
            <x-gcego-icon name="heroicon:calendar-days" class="size-4 shrink-0 text-muted-foreground transition group-hover/trigger:text-foreground" decorative />
            <span class="truncate" x-bind:class="{ 'text-muted-foreground': empty, 'text-foreground': ! empty }" x-text="summary">{{ $config['labels']['placeholder'] }}</span>
        </button>
        <button type="button" x-show="! empty" x-on:click="clear()" class="absolute right-2 top-1/2 inline-flex size-6 -translate-y-1/2 items-center justify-center rounded-full text-muted-foreground transition hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring" style="display: none" aria-label="{{ __('gcego-tables::messages.clear_date') }}">
            <x-gcego-icon name="heroicon:x-mark" class="size-3.5" decorative />
        </button>
    </div>

    <div id="{{ $id }}-calendar" x-show="open" x-cloak role="dialog" aria-modal="false" aria-label="{{ __('gcego-tables::messages.choose_date') }}"
        x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-[0.97] -translate-y-1" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="absolute left-0 top-full z-50 mt-1.5 flex origin-top overflow-hidden rounded-gcego-lg border border-border bg-popover text-popover-foreground shadow-gcego-overlay">
        <ul class="hidden w-36 shrink-0 flex-col gap-0.5 border-r border-border bg-surface-muted/50 p-1.5 sm:flex" aria-label="{{ __('gcego-tables::messages.quick_ranges') }}">
            @foreach ($presets as $preset => $label)
                <li><button type="button" x-on:click="preset(@js($preset))" class="w-full rounded-gcego-sm px-2.5 py-1.5 text-left text-xs font-medium text-muted-foreground transition hover:bg-surface hover:text-foreground hover:shadow-xs">{{ __('gcego-tables::messages.'.$label) }}</button></li>
            @endforeach
        </ul>
        <div class="w-72 p-3">
            <div class="mb-2 flex items-center justify-between">
                <button type="button" x-on:click="step(-1)" class="inline-flex size-8 items-center justify-center rounded-gcego-sm text-muted-foreground transition hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring" aria-label="{{ __('gcego-tables::messages.previous_month') }}"><x-gcego-icon name="heroicon:chevron-left" class="size-4" decorative /></button>
                <button type="button" x-on:click="cycleView()" class="inline-flex h-8 items-center gap-1 rounded-gcego-sm px-2.5 text-sm font-semibold text-foreground transition hover:bg-accent focus-visible:outline-2 focus-visible:outline-ring" aria-label="{{ __('gcego-tables::messages.change_view') }}">
                    <span aria-live="polite" x-text="heading"></span>
                    <x-gcego-icon name="heroicon:chevron-down" class="size-3.5 text-muted-foreground transition" x-bind:class="view !== 'days' && 'rotate-180'" decorative />
                </button>
                <button type="button" x-on:click="step(1)" class="inline-flex size-8 items-center justify-center rounded-gcego-sm text-muted-foreground transition hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring" aria-label="{{ __('gcego-tables::messages.next_month') }}"><x-gcego-icon name="heroicon:chevron-right" class="size-4" decorative /></button>
            </div>
            <div x-show="view === 'days'">
            <div class="grid grid-cols-7 text-center text-[11px] font-medium uppercase tracking-wide text-muted-foreground" aria-hidden="true">
                <template x-for="weekday in weekdays"><span class="py-1" x-text="weekday"></span></template>
            </div>
            <div x-ref="grid" class="grid grid-cols-7 gap-y-0.5" x-on:keydown="onGridKey($event)" x-on:mouseleave="hover = null">
                <template x-for="day in days" x-bind:key="day.iso">
                    <div class="relative flex justify-center" x-bind:class="day.between ? 'bg-primary-soft' : ''">
                        <button type="button" x-bind:data-day="day.iso" x-bind:tabindex="day.focused ? 0 : -1" x-bind:aria-label="day.label" x-bind:aria-pressed="day.selected ? 'true' : 'false'" x-bind:aria-current="day.today ? 'date' : null"
                            x-on:click="pick(day.iso)" x-on:mouseenter="hover = new Date(day.iso + 'T00:00:00')"
                            x-bind:class="{
                                'bg-primary text-primary-foreground shadow-sm hover:bg-primary-hover': day.selected,
                                'text-muted-foreground/60': day.outside && ! day.selected,
                                'text-foreground hover:bg-accent': ! day.selected && ! day.outside,
                                'font-semibold ring-1 ring-inset ring-primary/50': day.today && ! day.selected,
                            }"
                            class="relative inline-flex size-9 items-center justify-center rounded-gcego text-sm tabular-nums transition focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring"
                            x-text="day.day"></button>
                    </div>
                </template>
            </div>
            </div>
            <div x-show="view === 'months'" class="grid grid-cols-3 gap-1.5 py-1" style="display: none">
                <template x-for="item in monthNames" x-bind:key="item.index">
                    <button type="button" x-on:click="chooseMonth(item.index)" x-bind:aria-pressed="item.selected ? 'true' : 'false'"
                        x-bind:class="item.selected ? 'bg-primary text-primary-foreground hover:bg-primary-hover' : (item.current ? 'bg-accent text-foreground' : 'text-foreground hover:bg-accent')"
                        class="inline-flex h-12 items-center justify-center rounded-gcego text-sm font-medium tabular-nums transition focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring" x-text="item.label"></button>
                </template>
            </div>
            <div x-show="view === 'years'" class="grid grid-cols-3 gap-1.5 py-1" style="display: none">
                <template x-for="item in years" x-bind:key="item.year">
                    <button type="button" x-on:click="chooseYear(item.year)" x-bind:aria-pressed="item.selected ? 'true' : 'false'"
                        x-bind:class="{ 'bg-primary text-primary-foreground hover:bg-primary-hover': item.selected, 'bg-accent text-foreground': item.current && ! item.selected, 'text-foreground hover:bg-accent': ! item.current && ! item.selected, 'ring-1 ring-inset ring-primary/50': item.thisYear && ! item.selected }"
                        class="inline-flex h-12 items-center justify-center rounded-gcego text-sm font-medium tabular-nums transition focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring" x-text="item.year"></button>
                </template>
            </div>
            <div class="mt-3 flex items-center justify-between border-t border-border pt-2">
                <button type="button" x-on:click="preset('today')" class="inline-flex h-7 items-center rounded-gcego-sm px-2 text-xs font-medium text-primary transition hover:bg-primary-soft">{{ __('gcego-tables::messages.preset_today') }}</button>
                <div class="flex gap-1">
                    <button type="button" x-on:click="clear()" x-bind:disabled="empty" class="inline-flex h-7 items-center rounded-gcego-sm px-2 text-xs font-medium text-muted-foreground transition hover:bg-accent hover:text-foreground disabled:opacity-40">{{ __('gcego-tables::messages.clear') }}</button>
                    <button type="button" x-on:click="hide(true)" class="inline-flex h-7 items-center rounded-gcego-sm px-2 text-xs font-medium text-foreground transition hover:bg-accent">{{ __('gcego-tables::messages.done') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
