{{-- gcego-panels::states.home — the home of a panel without a dashboard. --}}
@php($presets = \Gcego\Panels\Presets\PanelPresets::class)
<div class="{{ implode(' ', $presets::EmptyWrapper) }}">
    <h1 class="text-2xl font-semibold tracking-tight text-gcego-text">{{ $title }}</h1>
    <div class="{{ implode(' ', $presets::Empty) }}">
        <p class="{{ implode(' ', $presets::EmptyTitle) }}">{{ __('gcego-panels::messages.welcome', ['panel' => $title]) }}</p>
        <p class="{{ implode(' ', $presets::EmptyText) }}">{{ $description ?? __('gcego-panels::messages.no_dashboard') }}</p>
    </div>
</div>
