<div class="{{ $styles('empty') }}" role="status" data-gcego-table-empty>
    @if ($frame->state->isFiltered())
        <span class="{{ implode(' ', $presets::EmptyIcon) }}">{{ $icons->svg('tables.no-results', 'size-6') }}</span>
        <p class="text-base font-semibold tracking-tight text-foreground">{{ __('gcego-tables::messages.no_results') }}</p>
        <p class="max-w-sm text-sm text-muted-foreground">{{ __('gcego-tables::messages.no_results_hint') }}</p>
        <button type="button" wire:click="resetTable" class="{{ implode(' ', $presets::ToolButton) }} mt-4">{{ $icons->svg('tables.reset', 'size-4') }}{{ __('gcego-tables::messages.clear_all') }}</button>
    @else
        <span class="{{ implode(' ', $presets::EmptyIcon) }}">{{ $icons->svg('tables.empty', 'size-6') }}</span>
        <p class="text-base font-semibold tracking-tight text-foreground">{{ __($table->get('empty_heading') ?? 'gcego-tables::messages.empty') }}</p>
        @if ($table->get('empty_description'))
            <p class="max-w-sm text-sm text-muted-foreground">{{ __($table->get('empty_description')) }}</p>
        @endif
    @endif
</div>
