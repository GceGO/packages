{{--
    <x-gcego-tables::listbox id="…" binding="filters.status" :options="['active' => 'Active']" :value="$state" multiple labelledby="…-label" />

    An accessible listbox (WAI-ARIA listbox pattern: arrows, Home/End, type-ahead, Enter/Space, Esc) in place of
    a native <select>. It writes to Livewire with $wire.set(binding) and follows Livewire back (gcegoListbox in
    gcego/chroma resources/js/controls.js). wire:ignore.self keeps it open across re-renders.
--}}
@props([
    'id',
    'options' => [],
    'value' => null,
    'binding' => null,
    'multiple' => false,
    'placeholder' => null,
    'labelledby' => null,
    'cast' => null,
    'emptyValue' => null,
    'fallback' => null,
    'size' => 'md',
    'align' => 'left',
    'upward' => false,
    'clearable' => true,
])
@php
    $list = [];
    foreach ($options as $optionValue => $optionLabel) {
        $list[] = ['value' => (string) $optionValue, 'label' => (string) __($optionLabel)];
    }
    $selected = is_array($value) ? array_map('strval', $value) : (($value === null || $value === '' || ($emptyValue !== null && (string) $value === (string) $emptyValue)) ? ($fallback !== null ? [(string) $fallback] : []) : [(string) $value]);
    $labels = array_values(array_map(fn ($option) => $option['label'], array_filter($list, fn ($option) => in_array($option['value'], $selected, true))));
    $placeholder ??= __('gcego-tables::messages.any');
    $initial = $labels === [] ? $placeholder : (count($labels) <= 2 ? implode(', ', $labels) : __('gcego-tables::messages.n_selected', ['count' => count($labels)]));
    $config = array_filter([
        'id' => $id,
        'options' => $list,
        'value' => $value,
        'binding' => $binding,
        'multiple' => (bool) $multiple,
        'placeholder' => $placeholder,
        'many' => __('gcego-tables::messages.n_selected'),
        'cast' => $cast,
        'emptyValue' => $emptyValue,
        'fallback' => $fallback,
    ], fn ($item) => $item !== null);
    $height = $size === 'sm' ? 'h-8 text-sm' : 'h-10 text-sm';
@endphp
<div class="relative" wire:ignore.self x-data="gcegoListbox(@js($config))" x-on:click.outside="hide(false)" {{ $attributes->only(['data-gcego-listbox']) }}>
    <button type="button" id="{{ $id }}" x-ref="trigger" aria-haspopup="listbox" aria-expanded="false" x-bind:aria-expanded="open.toString()" aria-controls="{{ $id }}-listbox"
        @if ($labelledby) aria-labelledby="{{ $labelledby }} {{ $id }}" @endif
        x-on:click="toggle()" x-on:keydown="onTriggerKey($event)"
        class="group/trigger flex w-full min-w-0 items-center justify-between gap-2 rounded-gcego border border-input bg-surface pl-3 pr-2 text-left {{ $height }} text-foreground shadow-xs transition hover:border-foreground/40 focus-visible:border-ring focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-ring/15 aria-expanded:border-ring aria-expanded:ring-4 aria-expanded:ring-ring/15">
        <span class="truncate {{ $labels === [] ? 'text-muted-foreground' : '' }}" x-bind:class="{ 'text-muted-foreground': empty, 'text-foreground': ! empty }" x-text="summary">{{ $initial }}</span>
        <span class="flex shrink-0 items-center gap-1">
            @if ($multiple)
                <span x-show="values.length > 1" x-text="values.length" class="inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[11px] font-semibold leading-5 text-primary-foreground" @if (count($selected) <= 1) style="display: none" @endif>{{ count($selected) }}</span>
            @endif
            <x-gcego-icon name="heroicon:chevron-up-down" class="size-4 text-muted-foreground transition group-hover/trigger:text-foreground" decorative />
        </span>
    </button>

    <div x-show="open" x-cloak
        x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-[0.97] {{ $upward ? 'translate-y-1' : '-translate-y-1' }}" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="absolute z-50 {{ $upward ? 'bottom-full mb-1.5 origin-bottom' : 'top-full mt-1.5 origin-top' }} {{ $align === 'right' ? 'right-0' : 'left-0' }} w-max min-w-full max-w-xs rounded-gcego-lg border border-border bg-popover p-1 text-popover-foreground shadow-gcego-overlay">
        <ul id="{{ $id }}-listbox" x-ref="listbox" role="listbox" tabindex="-1"
            @if ($multiple) aria-multiselectable="true" @endif
            @if ($labelledby) aria-labelledby="{{ $labelledby }}" @endif
            x-bind:aria-activedescendant="open && active >= 0 ? optionId(active) : null"
            x-on:keydown="onListKey($event)"
            class="max-h-64 overflow-y-auto overscroll-contain outline-none [scrollbar-width:thin]">
            <template x-for="(option, index) in visible" x-bind:key="option.value">
                <li x-bind:id="optionId(index)" role="option" x-bind:aria-selected="isSelected(option.value).toString()"
                    x-on:click="choose(index)" x-on:mousemove="active = index"
                    x-bind:class="active === index ? 'bg-accent' : ''"
                    class="flex cursor-pointer select-none items-center gap-2.5 rounded-gcego px-2.5 py-2 text-sm text-foreground transition-colors">
                    @if ($multiple)
                        <span class="inline-flex size-4 shrink-0 items-center justify-center rounded-[5px] border transition"
                            x-bind:class="isSelected(option.value) ? 'border-primary bg-primary text-primary-foreground' : 'border-input bg-surface text-transparent'">
                            <x-gcego-icon name="heroicon:check" class="size-3" :stroke-width="3.5" decorative />
                        </span>
                        <span class="truncate" x-text="option.label"></span>
                    @else
                        <span class="flex-1 truncate" x-bind:class="isSelected(option.value) && 'font-medium'" x-text="option.label"></span>
                        <span class="text-primary" x-show="isSelected(option.value)"><x-gcego-icon name="heroicon:check" class="size-4" :stroke-width="2.5" decorative /></span>
                    @endif
                </li>
            </template>
        </ul>
        @if ($multiple || ($clearable && $emptyValue === null && $fallback === null))
            <div class="mt-1 flex items-center justify-between gap-2 border-t border-border px-1 pt-1">
                <button type="button" x-on:click="clear()" x-bind:disabled="empty" class="inline-flex h-7 items-center rounded-gcego-sm px-2 text-xs font-medium text-muted-foreground transition hover:bg-accent hover:text-foreground disabled:pointer-events-none disabled:opacity-40">{{ __('gcego-tables::messages.clear') }}</button>
                @if ($multiple)
                    <button type="button" x-on:click="hide(true)" class="inline-flex h-7 items-center rounded-gcego-sm px-2 text-xs font-medium text-foreground transition hover:bg-accent">{{ __('gcego-tables::messages.done') }}</button>
                @endif
            </div>
        @endif
    </div>
</div>
