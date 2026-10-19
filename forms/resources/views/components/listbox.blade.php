{{--
    <x-gcego-forms::listbox id="…" :binding="$binding" :options="$options" :value="$value" :live="$live" model="data.kind" />

    An accessible listbox (WAI-ARIA: arrows, Home/End, type-ahead, Enter/Space, Esc; optional search) in place of a
    native select. The field's binding stays on a visually hidden native select that the listbox drives, so deferred,
    live and debounced wire:model bindings and plain name="" submits work unchanged (gcegoListbox in gcego/chroma
    resources/js/controls.js). wire:ignore.self keeps it open across Livewire re-renders.
--}}
@props([
    'id',
    'binding',
    'options' => [],
    'value' => null,
    'live' => false,
    'model' => null,
    'multiple' => false,
    'searchable' => false,
    'placeholder' => null,
    'disabled' => false,
    'required' => false,
    'invalid' => false,
    'describedby' => null,
])
@php
    $list = [];
    foreach ($options as $option) {
        $list[] = ['value' => (string) $option->value, 'label' => (string) __($option->label), 'disabled' => (bool) $option->disabled];
    }
    $selected = is_array($value) ? array_map('strval', $value) : ($value === null || $value === '' ? [] : [(string) $value]);
    $labels = array_values(array_map(fn (array $option): string => $option['label'], array_filter($list, fn (array $option): bool => in_array($option['value'], $selected, true))));
    $placeholder ??= __('gcego-forms::messages.select');
    $initial = $labels === [] ? $placeholder : (count($labels) <= 2 ? implode(', ', $labels) : __('gcego-forms::messages.n_selected', ['count' => count($labels)]));
    $config = array_filter([
        'id' => $id,
        'options' => $list,
        'value' => $value,
        'binding' => $live ? $model : null,
        'multiple' => (bool) $multiple,
        'placeholder' => $placeholder,
        'many' => __('gcego-forms::messages.n_selected'),
        'disabled' => (bool) $disabled,
    ], fn (mixed $item): bool => $item !== null);
@endphp
<div class="relative" wire:ignore.self x-data="gcegoListbox(@js($config))" x-on:click.outside="hide(false)" data-gcego-listbox>
    <select x-ref="native" {{ $binding }} @if ($multiple) multiple @endif @disabled($disabled) tabindex="-1" aria-hidden="true" class="sr-only">
        @unless ($multiple)
            <option value=""></option>
        @endunless
        @foreach ($list as $option)
            <option value="{{ $option['value'] }}" @disabled($option['disabled']) @if (! $live && in_array($option['value'], $selected, true)) selected @endif>{{ $option['label'] }}</option>
        @endforeach
    </select>

    <button type="button" id="{{ $id }}" x-ref="trigger" aria-haspopup="listbox" aria-expanded="false" x-bind:aria-expanded="open.toString()" aria-controls="{{ $id }}-listbox"
        @if ($describedby) aria-describedby="{{ $describedby }}" @endif
        @if ($invalid) aria-invalid="true" @endif
        @if ($required) aria-required="true" @endif
        @disabled($disabled)
        x-on:click="toggle()" x-on:keydown="onTriggerKey($event)"
        class="group/trigger flex min-h-10 w-full min-w-0 items-center justify-between gap-2 rounded-gcego border border-border bg-background shadow-[inset_0_1px_2px_rgb(0_0_0/0.05)] py-1.5 pl-3.5 pr-2 text-left text-sm text-foreground transition duration-150 hover:border-border-strong/60 focus-visible:border-primary focus-visible:bg-surface focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/15 aria-expanded:border-primary aria-expanded:bg-surface aria-expanded:ring-4 aria-expanded:ring-primary/15 aria-invalid:border-danger/70 disabled:cursor-not-allowed disabled:opacity-60">
        <span class="truncate {{ $labels === [] ? 'text-muted-foreground' : '' }}" x-bind:class="{ 'text-muted-foreground': empty, 'text-foreground': ! empty }" x-text="summary">{{ $initial }}</span>
        <span class="flex shrink-0 items-center gap-1.5">
            @if ($multiple)
                <span x-show="values.length > 1" x-text="values.length" class="inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[11px] font-semibold leading-5 text-primary-foreground" @if (count($selected) <= 1) style="display: none" @endif>{{ count($selected) }}</span>
            @endif
            <x-gcego-icon name="heroicon:chevron-up-down" class="size-4 text-muted-foreground transition group-hover/trigger:text-foreground" decorative />
        </span>
    </button>

    <div x-show="open" x-cloak
        x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-[0.97] -translate-y-1" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        x-on:keydown="onListKey($event)"
        class="absolute left-0 top-full z-50 mt-1.5 w-full min-w-56 origin-top overflow-hidden rounded-gcego-lg border border-border bg-popover text-popover-foreground shadow-gcego-overlay">
        @if ($searchable)
            <div class="relative border-b border-border">
                <x-gcego-icon name="heroicon:magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" decorative />
                <input type="text" x-ref="search" x-model="search" x-on:input="active = firstEnabled(0, 1)" autocomplete="off" spellcheck="false"
                    role="combobox" aria-autocomplete="list" aria-expanded="true" aria-controls="{{ $id }}-listbox" x-bind:aria-activedescendant="active >= 0 ? optionId(active) : null"
                    aria-label="{{ __('gcego-forms::messages.search_options') }}" placeholder="{{ __('gcego-forms::messages.search_options') }}"
                    class="h-10 w-full border-0 bg-transparent pl-9 pr-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-0">
            </div>
        @endif
        <ul id="{{ $id }}-listbox" x-ref="listbox" role="listbox" tabindex="-1"
            @if ($multiple) aria-multiselectable="true" @endif
            aria-labelledby="{{ $id }}-label"
            x-bind:aria-activedescendant="open && active >= 0 ? optionId(active) : null"
            class="max-h-64 overflow-y-auto overscroll-contain p-1 outline-none [scrollbar-width:thin]">
            <template x-for="(option, index) in visible" x-bind:key="option.value">
                <li x-bind:id="optionId(index)" role="option" x-bind:aria-selected="isSelected(option.value).toString()" x-bind:aria-disabled="option.disabled ? 'true' : null"
                    x-on:click="choose(index)" x-on:mousemove="active = index"
                    x-bind:class="{ 'bg-accent': active === index, 'cursor-not-allowed opacity-40': option.disabled, 'cursor-pointer': ! option.disabled }"
                    class="flex select-none items-center gap-2.5 rounded-gcego px-2.5 py-2 text-sm text-foreground transition-colors">
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
            <li x-show="visible.length === 0" role="presentation" class="px-2.5 py-6 text-center text-sm text-muted-foreground" style="display: none">{{ __('gcego-forms::messages.no_results') }}</li>
        </ul>
        @if ($multiple || ! $required)
            <div class="flex items-center justify-between gap-2 border-t border-border p-1">
                <button type="button" x-on:click="clear()" x-bind:disabled="empty" class="inline-flex h-7 items-center rounded-gcego-sm px-2 text-xs font-medium text-muted-foreground transition hover:bg-accent hover:text-foreground disabled:pointer-events-none disabled:opacity-40">{{ __('gcego-forms::messages.clear') }}</button>
                @if ($multiple)
                    <button type="button" x-on:click="hide(true)" class="inline-flex h-7 items-center rounded-gcego-sm px-2 text-xs font-medium text-foreground transition hover:bg-accent">{{ __('gcego-forms::messages.done') }}</button>
                @endif
            </div>
        @endif
    </div>
</div>
