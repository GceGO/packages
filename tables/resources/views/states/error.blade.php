{{-- A safe message only: the exception is reported, never shown. --}}
<div class="{{ implode(' ', $presets::ErrorState) }}" role="alert" data-gcego-table-error>
    <span class="mb-1 inline-flex size-14 items-center justify-center rounded-2xl border border-danger/20 bg-danger-soft text-danger ring-8 ring-danger-soft/40">{{ $icons->svg('tables.error', 'size-6') }}</span>
    <p class="text-base font-semibold tracking-tight text-foreground">{{ __('gcego-tables::messages.error') }}</p>
    <button type="button" wire:click="refresh" class="{{ implode(' ', $presets::ToolButton) }}">{{ $icons->svg('actions.refresh', 'size-4') }}{{ __('gcego-tables::messages.retry') }}</button>
</div>
