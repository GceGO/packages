{{--
    <x-gcego-forms::date-picker id="…" :binding="$binding" :value="$value" :live="$live" model="data.starts_on" mode="date|datetime|time" />

    A calendar / clock popover in place of native date, datetime-local and time inputs: month grid with month and year
    views (arrows, PageUp/PageDown, Home/End, Esc), hour and minute columns, Today / Now and Clear. The field's binding
    stays on a hidden input in the field's wire format ("Y-m-d", "Y-m-d\TH:i", "H:i"), so wire:model modifiers and plain
    submits work unchanged (gcegoDatePicker in gcego/chroma resources/js/controls.js).
--}}
@props([
    'id',
    'binding',
    'value' => null,
    'live' => false,
    'model' => null,
    'mode' => 'date',
    'min' => null,
    'max' => null,
    'minuteStep' => 5,
    'placeholder' => null,
    'disabled' => false,
    'required' => false,
    'invalid' => false,
    'describedby' => null,
    'label' => '',
])
@php
    $value = is_scalar($value) ? (string) $value : null;
    $placeholder ??= __('gcego-forms::messages.pick_'.$mode);
    $config = [
        'id' => $id,
        'mode' => $mode,
        'value' => $value,
        'binding' => $live ? $model : null,
        'min' => $min,
        'max' => $max,
        'minuteStep' => $minuteStep,
        'disabled' => (bool) $disabled,
        'locale' => str_replace('_', '-', app()->getLocale()),
        'labels' => ['placeholder' => $placeholder],
    ];
    $icon = $mode === 'time' ? 'heroicon:clock' : 'heroicon:calendar-days';
    $cell = 'inline-flex items-center justify-center rounded-gcego text-sm tabular-nums transition focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring disabled:pointer-events-none disabled:opacity-30';
@endphp
<div class="relative" wire:ignore.self x-data="gcegoDatePicker(@js($config))" x-on:click.outside="open && hide(false)" x-on:keydown.escape.stop="open && hide(true)" data-gcego-date-picker="{{ $mode }}">
    <input type="hidden" x-ref="native" {{ $binding }} @unless ($live) value="{{ $value }}" @endunless>

    <div class="relative">
        <button type="button" id="{{ $id }}" x-ref="trigger" x-on:click="open ? hide(false) : show()" aria-haspopup="dialog" aria-expanded="false" x-bind:aria-expanded="open.toString()" aria-controls="{{ $id }}-picker"
            @if ($describedby) aria-describedby="{{ $describedby }}" @endif
            @if ($invalid) aria-invalid="true" @endif
            @if ($required) aria-required="true" @endif
            @disabled($disabled)
            class="group/trigger flex min-h-10 w-full min-w-0 items-center gap-2.5 rounded-gcego border border-border bg-background shadow-[inset_0_1px_2px_rgb(0_0_0/0.05)] py-2 pl-3.5 pr-9 text-left text-sm text-foreground transition duration-150 hover:border-border-strong/60 focus-visible:border-primary focus-visible:bg-surface focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/15 aria-expanded:border-primary aria-expanded:bg-surface aria-expanded:ring-4 aria-expanded:ring-primary/15 aria-invalid:border-danger/70 disabled:cursor-not-allowed disabled:opacity-60">
            <x-gcego-icon :name="$icon" class="size-4 shrink-0 text-muted-foreground transition group-hover/trigger:text-foreground" decorative />
            <span class="truncate {{ $value === null || $value === '' ? 'text-muted-foreground' : '' }}" x-bind:class="{ 'text-muted-foreground': empty, 'text-foreground': ! empty }" x-text="summary">{{ $value !== null && $value !== '' ? $value : $placeholder }}</span>
        </button>
        @unless ($disabled)
            <button type="button" x-show="! empty" x-on:click="clear()" class="absolute right-2 top-1/2 inline-flex size-6 -translate-y-1/2 items-center justify-center rounded-full text-muted-foreground transition hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring" @if ($value === null || $value === '') style="display: none" @endif aria-label="{{ __('gcego-forms::messages.clear_value', ['label' => $label]) }}">
                <x-gcego-icon name="heroicon:x-mark" class="size-3.5" decorative />
            </button>
        @endunless
    </div>

    <div id="{{ $id }}-picker" x-show="open" x-cloak role="dialog" aria-modal="false" aria-label="{{ $mode === 'time' ? __('gcego-forms::messages.choose_time') : __('gcego-forms::messages.choose_date') }}"
        x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-[0.97] -translate-y-1" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="absolute left-0 top-full z-50 mt-1.5 max-w-[calc(100vw-2rem)] origin-top overflow-hidden rounded-gcego-lg border border-border bg-popover text-popover-foreground shadow-gcego-overlay">
        <div class="flex">
            @if ($mode !== 'time')
                <div class="w-72 p-3">
                    <div class="mb-2 flex items-center justify-between gap-1">
                        <button type="button" x-on:click="step(-1)" class="inline-flex size-8 items-center justify-center rounded-gcego-sm text-muted-foreground transition hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring" aria-label="{{ __('gcego-forms::messages.previous') }}"><x-gcego-icon name="heroicon:chevron-left" class="size-4" decorative /></button>
                        <button type="button" x-on:click="cycleView()" class="inline-flex h-8 items-center gap-1 rounded-gcego-sm px-2.5 text-sm font-semibold text-foreground transition hover:bg-accent focus-visible:outline-2 focus-visible:outline-ring" aria-label="{{ __('gcego-forms::messages.change_view') }}">
                            <span aria-live="polite" x-text="heading"></span>
                            <x-gcego-icon name="heroicon:chevron-down" class="size-3.5 text-muted-foreground transition" x-bind:class="view !== 'days' && 'rotate-180'" decorative />
                        </button>
                        <button type="button" x-on:click="step(1)" class="inline-flex size-8 items-center justify-center rounded-gcego-sm text-muted-foreground transition hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring" aria-label="{{ __('gcego-forms::messages.next') }}"><x-gcego-icon name="heroicon:chevron-right" class="size-4" decorative /></button>
                    </div>

                    <div x-show="view === 'days'">
                        <div class="grid grid-cols-7 text-center text-[11px] font-medium uppercase tracking-wide text-muted-foreground" aria-hidden="true">
                            <template x-for="weekday in weekdays"><span class="py-1" x-text="weekday"></span></template>
                        </div>
                        <div x-ref="grid" class="grid grid-cols-7 gap-y-0.5" x-on:keydown="onGridKey($event)">
                            <template x-for="day in days" x-bind:key="day.iso">
                                <div class="flex justify-center">
                                    <button type="button" x-bind:data-day="day.iso" x-bind:tabindex="day.focused ? 0 : -1" x-bind:aria-label="day.label" x-bind:aria-pressed="day.selected ? 'true' : 'false'" x-bind:aria-current="day.today ? 'date' : null" x-bind:disabled="day.disabled"
                                        x-on:click="pick(day.iso)"
                                        x-bind:class="{
                                            'bg-primary text-primary-foreground shadow-sm hover:bg-primary-hover': day.selected,
                                            'text-muted-foreground/60': day.outside && ! day.selected,
                                            'text-foreground hover:bg-accent': ! day.selected && ! day.outside,
                                            'font-semibold ring-1 ring-inset ring-primary/50': day.today && ! day.selected,
                                        }"
                                        class="{{ $cell }} size-9" x-text="day.day"></button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div x-show="view === 'months'" class="grid grid-cols-3 gap-1.5 py-1" style="display: none">
                        <template x-for="item in monthNames" x-bind:key="item.index">
                            <button type="button" x-on:click="chooseMonth(item.index)" x-bind:aria-pressed="item.selected ? 'true' : 'false'"
                                x-bind:class="item.selected ? 'bg-primary text-primary-foreground hover:bg-primary-hover' : (item.current ? 'bg-accent text-foreground' : 'text-foreground hover:bg-accent')"
                                class="{{ $cell }} h-12 font-medium" x-text="item.label"></button>
                        </template>
                    </div>

                    <div x-show="view === 'years'" class="grid grid-cols-3 gap-1.5 py-1" style="display: none">
                        <template x-for="item in years" x-bind:key="item.year">
                            <button type="button" x-on:click="chooseYear(item.year)" x-bind:aria-pressed="item.selected ? 'true' : 'false'"
                                x-bind:class="{ 'bg-primary text-primary-foreground hover:bg-primary-hover': item.selected, 'bg-accent text-foreground': item.current && ! item.selected, 'text-foreground hover:bg-accent': ! item.current && ! item.selected, 'ring-1 ring-inset ring-primary/50': item.thisYear && ! item.selected }"
                                class="{{ $cell }} h-12 font-medium" x-text="item.year"></button>
                        </template>
                    </div>
                </div>
            @endif

            @if ($mode !== 'date')
                <div class="flex {{ $mode === 'time' ? 'w-44' : 'w-36 border-l border-border' }} flex-col">
                    <div class="grid grid-cols-2 border-b border-border text-center text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                        <span class="py-2.5">{{ __('gcego-forms::messages.hours') }}</span>
                        <span class="py-2.5">{{ __('gcego-forms::messages.minutes') }}</span>
                    </div>
                    <div class="grid h-64 grid-cols-2 divide-x divide-border">
                        <div x-ref="hours" class="flex flex-col gap-0.5 overflow-y-auto overscroll-contain p-1.5 [scrollbar-width:thin]" role="group" aria-label="{{ __('gcego-forms::messages.hours') }}">
                            <template x-for="item in hours" x-bind:key="item">
                                <button type="button" x-on:click="setHour(item)" x-bind:aria-pressed="hour === item ? 'true' : 'false'"
                                    x-bind:class="hour === item ? 'bg-primary text-primary-foreground hover:bg-primary-hover' : 'text-foreground hover:bg-accent'"
                                    class="{{ $cell }} h-8 shrink-0" x-text="pad(item)"></button>
                            </template>
                        </div>
                        <div x-ref="minutes" class="flex flex-col gap-0.5 overflow-y-auto overscroll-contain p-1.5 [scrollbar-width:thin]" role="group" aria-label="{{ __('gcego-forms::messages.minutes') }}">
                            <template x-for="item in minutes" x-bind:key="item">
                                <button type="button" x-on:click="setMinute(item)" x-bind:aria-pressed="minute === item ? 'true' : 'false'"
                                    x-bind:class="minute === item ? 'bg-primary text-primary-foreground hover:bg-primary-hover' : 'text-foreground hover:bg-accent'"
                                    class="{{ $cell }} h-8 shrink-0" x-text="pad(item)"></button>
                            </template>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="flex items-center justify-between gap-2 border-t border-border px-3 py-2">
            @if ($mode === 'date')
                <button type="button" x-on:click="preset('today')" class="inline-flex h-7 items-center rounded-gcego-sm px-2 text-xs font-medium text-primary transition hover:bg-primary-soft">{{ __('gcego-forms::messages.today') }}</button>
            @else
                <button type="button" x-on:click="now()" class="inline-flex h-7 items-center rounded-gcego-sm px-2 text-xs font-medium text-primary transition hover:bg-primary-soft">{{ __('gcego-forms::messages.now') }}</button>
            @endif
            <div class="flex gap-1">
                <button type="button" x-on:click="clear()" x-bind:disabled="empty" class="inline-flex h-7 items-center rounded-gcego-sm px-2 text-xs font-medium text-muted-foreground transition hover:bg-accent hover:text-foreground disabled:opacity-40">{{ __('gcego-forms::messages.clear') }}</button>
                <button type="button" x-on:click="hide(true)" class="inline-flex h-7 items-center rounded-gcego-sm px-2 text-xs font-medium text-foreground transition hover:bg-accent">{{ __('gcego-forms::messages.done') }}</button>
            </div>
        </div>
    </div>
</div>
