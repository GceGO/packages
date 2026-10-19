@if ($error)
    <div class="{{ implode(' ', $presets::ErrorState) }}" role="alert" data-gcego-widget-error>
        {{ $icons->svg('widgets.error', 'size-6') }}
        <p>{{ __('gcego-widgets::messages.error') }}</p>
        @if ($frame->live)
            <button type="button" wire:click="refresh" class="{{ implode(' ', $presets::Button) }}">{{ __('gcego-widgets::messages.retry') }}</button>
        @endif
    </div>
@else
    <div class="{{ $styles('empty') }}" role="status" data-gcego-widget-empty>
        {{ $icons->svg('widgets.empty', 'size-6') }}
        <p>{{ __($widget->emptyText()) }}</p>
    </div>
@endif
