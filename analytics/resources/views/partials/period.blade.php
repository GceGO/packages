{{-- gcego-analytics::partials.period — period and comparison controls (labelled, keyboard reachable). --}}
@php($p = \Gcego\Analytics\Presets\AnalyticsPresets::class)
<label class="flex flex-col gap-1 {{ implode(' ', $p::FilterLabel) }}">
    {{ __('gcego-analytics::messages.period') }}
    <select wire:model.live="range" class="{{ implode(' ', $p::Control) }}">
        <option value="">{{ __('gcego-analytics::messages.presets.'.$default) }}</option>
        @foreach ($presets as $preset)
            @if ($preset !== $default)
                <option value="{{ $preset }}">{{ __('gcego-analytics::messages.presets.'.$preset) }}</option>
            @endif
        @endforeach
    </select>
</label>
@if ($range === 'custom')
    <label class="flex flex-col gap-1 {{ implode(' ', $p::FilterLabel) }}">
        {{ __('gcego-analytics::messages.from') }}
        <input type="date" wire:model.live="from" class="{{ implode(' ', $p::Control) }}">
    </label>
    <label class="flex flex-col gap-1 {{ implode(' ', $p::FilterLabel) }}">
        {{ __('gcego-analytics::messages.to') }}
        <input type="date" wire:model.live="to" class="{{ implode(' ', $p::Control) }}">
    </label>
@endif
