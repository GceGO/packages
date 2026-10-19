{{-- Column manager: switches to show or hide, buttons to reorder (a keyboard-accessible alternative to drag and drop). --}}
<div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false">
    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()" aria-expanded="false" aria-controls="{{ $frame->id('columns') }}" class="{{ implode(' ', $presets::ToolButton) }}" title="{{ __('gcego-tables::messages.move_options') }}">
        {{ $icons->svg('tables.columns', 'size-4') }}<span class="hidden sm:inline">{{ __('gcego-tables::messages.columns') }}</span><span class="sr-only sm:hidden">{{ __('gcego-tables::messages.columns') }}</span>
        @if ($frame->state->hidden !== [])<span class="{{ implode(' ', $presets::CountPill) }}" title="{{ trans_choice('gcego-tables::messages.hidden_columns', count($frame->state->hidden), ['count' => count($frame->state->hidden)]) }}">{{ count($frame->state->order) - count($frame->state->hidden) }}/{{ count($frame->state->order) }}</span>@endif
    </button>
    <div id="{{ $frame->id('columns') }}" x-show="open" x-cloak
        x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95 -translate-y-1" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
        class="{{ implode(' ', $presets::Panel) }}" role="group" aria-label="{{ __('gcego-tables::messages.columns') }}">
        <p class="{{ implode(' ', $presets::PanelHeading) }}">{{ __('gcego-tables::messages.columns') }}</p>
        <ul class="flex max-h-80 flex-col gap-0.5 overflow-y-auto pr-1 [scrollbar-width:thin]">
            @foreach ($frame->state->order as $name)
                @php($column = $schema->columns[$name])
                @php($shown = ! in_array($name, $frame->state->hidden, true))
                <li class="group/item flex items-center justify-between gap-2 rounded-gcego px-2 py-1.5 transition hover:bg-accent" wire:key="{{ $frame->id('column-'.$name) }}">
                    <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-3 text-sm {{ $column->isHideable() ? '' : 'cursor-not-allowed' }}">
                        <input type="checkbox" class="peer sr-only" @checked($shown) @disabled(! $column->isHideable()) wire:click="toggleColumn({{ \Illuminate\Support\Js::from($name) }})">
                        <span class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full bg-border-strong/60 transition peer-checked:bg-primary peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring peer-disabled:opacity-50 after:absolute after:left-0.5 after:size-4 after:rounded-full after:bg-surface after:shadow-sm after:transition-transform peer-checked:after:translate-x-4" aria-hidden="true"></span>
                        <span class="truncate {{ $shown ? 'text-foreground' : 'text-muted-foreground' }}">{{ __($column->label()) }}</span>
                    </label>
                    <span class="flex shrink-0 gap-0.5 opacity-60 transition group-hover/item:opacity-100 group-focus-within/item:opacity-100">
                        <button type="button" wire:click="moveColumn({{ \Illuminate\Support\Js::from($name) }}, -1)" @disabled($loop->first) class="inline-flex size-7 items-center justify-center rounded-gcego-sm text-muted-foreground transition hover:bg-surface hover:text-foreground disabled:opacity-30" aria-label="{{ __('gcego-tables::messages.move_up', ['column' => __($column->label())]) }}">{{ $icons->svg('tables.move-up', 'size-3.5') }}</button>
                        <button type="button" wire:click="moveColumn({{ \Illuminate\Support\Js::from($name) }}, 1)" @disabled($loop->last) class="inline-flex size-7 items-center justify-center rounded-gcego-sm text-muted-foreground transition hover:bg-surface hover:text-foreground disabled:opacity-30" aria-label="{{ __('gcego-tables::messages.move_down', ['column' => __($column->label())]) }}">{{ $icons->svg('tables.move-down', 'size-3.5') }}</button>
                    </span>
                </li>
            @endforeach
        </ul>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 border-t border-border px-1 pt-2">
            <div class="{{ implode(' ', $presets::Segmented) }}" role="group" aria-label="{{ __('gcego-tables::messages.density') }}">
                @foreach (\Gcego\Tables\Table::Densities as $density)
                    <button type="button" wire:click="setDensity({{ \Illuminate\Support\Js::from($density) }})" aria-pressed="{{ $frame->state->density === $density ? 'true' : 'false' }}" class="{{ implode(' ', $presets::SegmentButton) }} w-auto px-2.5 text-xs font-medium">{{ __('gcego-tables::messages.density_'.$density) }}</button>
                @endforeach
            </div>
            <button type="button" wire:click="resetColumns" class="{{ implode(' ', $presets::RowButton) }} text-xs">{{ $icons->svg('tables.reset', 'size-3.5') }}{{ __('gcego-tables::messages.reset_columns') }}</button>
        </div>
    </div>
</div>
