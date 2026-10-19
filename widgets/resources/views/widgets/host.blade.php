{{-- The range and refresh controls dock in the card's top corner; the card header (WidgetPresets::Header) leaves room for them. --}}
<div class="group/widget relative flex flex-col gap-2" data-gcego-widget-host="{{ $widget }}" data-gcego-toolbar="{{ $hasRange ? 'range' : 'refresh' }}" @if ($poll) wire:poll.{{ $poll }}s.visible="refresh" @endif wire:loading.attr="aria-busy" wire:target="refresh,range,from,to,olderPage,newerPage">
        <div class="{{ implode(' ', \Gcego\Widgets\Presets\WidgetPresets::Toolbar) }}">
            @if ($hasRange)
                <label class="sr-only" for="{{ $this->getId() }}-range">{{ __('gcego-widgets::messages.range_for', ['widget' => __($title)]) }}</label>
                <select id="{{ $this->getId() }}-range" wire:model.live="range" class="{{ implode(' ', \Gcego\Widgets\Presets\WidgetPresets::Control) }}">
                    @foreach ($presets as $preset)
                        <option value="{{ $preset }}">{{ __('gcego-widgets::messages.range_'.$preset) }}</option>
                    @endforeach
                    @if ($custom)
                        <option value="custom">{{ __('gcego-widgets::messages.range_custom') }}</option>
                    @endif
                </select>
            @endif
            <button type="button" wire:click="refresh" wire:loading.attr="disabled" wire:target="refresh" class="{{ implode(' ', \Gcego\Widgets\Presets\WidgetPresets::Button) }}" aria-label="{{ __('gcego-widgets::messages.refresh', ['widget' => __($title)]) }}" title="{{ __('gcego-widgets::messages.refresh', ['widget' => __($title)]) }}">
                {{ app(\Gcego\Tables\Support\Icons::class)->svg('widgets.refresh') }}
            </button>
        </div>
    @if ($hasRange && $custom && $range === 'custom')
        <div class="{{ implode(' ', \Gcego\Widgets\Presets\WidgetPresets::CustomRange) }}">
            <label class="sr-only" for="{{ $this->getId() }}-from">{{ __('gcego-widgets::messages.from') }}</label>
            <input id="{{ $this->getId() }}-from" type="date" wire:model.blur="from" class="{{ implode(' ', \Gcego\Widgets\Presets\WidgetPresets::Control) }}">
            <label class="sr-only" for="{{ $this->getId() }}-to">{{ __('gcego-widgets::messages.to') }}</label>
            <input id="{{ $this->getId() }}-to" type="date" wire:model.blur="to" class="{{ implode(' ', \Gcego\Widgets\Presets\WidgetPresets::Control) }}">
        </div>
    @endif
    @if ($notice)
        <p class="{{ implode(' ', \Gcego\Widgets\Presets\WidgetPresets::Notice) }}" role="status">{{ $notice }}</p>
    @endif
    <div class="relative flex-1">
        {{ $content }}
        <div wire:loading.flex wire:target="refresh,range,from,to,olderPage,newerPage" class="{{ implode(' ', \Gcego\Widgets\Presets\WidgetPresets::Loading) }}" role="status" style="display: none">
            <span class="{{ implode(' ', \Gcego\Widgets\Presets\WidgetPresets::Spinner) }}" aria-hidden="true"></span>
            <span class="sr-only">{{ __('gcego-widgets::messages.loading') }}</span>
        </div>
    </div>
</div>
