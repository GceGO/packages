{{-- gcego-panels::partials.appearance — the user's own look, in the user menu: light, dark or system, and the main
     color (badges, active links, buttons). Applied at once through window.GcegoAppearance and remembered in this browser. --}}
@php($presets = \Gcego\Panels\Presets\PanelPresets::class)
@php($styling = app(\Gcego\Chroma\ChromaManager::class))
@php($accents = $styling->accents())
@if ($styling->allowsSwitching() || $accents !== [])
    <div x-data="{ mode: 'system', accent: null, init() { if (window.GcegoAppearance) { this.mode = GcegoAppearance.get(); this.accent = GcegoAppearance.accent ? GcegoAppearance.accent() : null } }, setMode(mode) { if (window.GcegoAppearance && GcegoAppearance.set(mode)) { this.mode = mode } }, setAccent(accent) { if (window.GcegoAppearance && GcegoAppearance.setAccent(accent)) { this.accent = accent } } }" x-on:gcego-appearance.window="mode = $event.detail.mode" x-on:gcego-accent.window="accent = $event.detail.accent" class="{{ implode(' ', $presets::Appearance) }}">
        @if ($styling->allowsSwitching())
            <p id="{{ $shell->id('appearance-mode') }}" class="{{ implode(' ', $presets::AppearanceLabel) }}">{{ __('gcego-panels::messages.appearance') }}</p>
            <div role="group" aria-labelledby="{{ $shell->id('appearance-mode') }}" class="{{ implode(' ', $presets::AppearanceModes) }}">
                @foreach (['light' => 'settings.light', 'dark' => 'settings.dark', 'system' => 'settings.system'] as $mode => $icon)
                    <button type="button" x-on:click="setMode('{{ $mode }}')" aria-pressed="false" x-bind:aria-pressed="(mode === '{{ $mode }}').toString()" class="{{ implode(' ', $presets::AppearanceMode) }}">
                        {{ $shell->svg($icon, 'size-4') }}
                        <span>{{ __('gcego-panels::messages.appearance_'.$mode) }}</span>
                    </button>
                @endforeach
            </div>
        @endif
        @if ($accents !== [])
            <p id="{{ $shell->id('appearance-accent') }}" class="{{ implode(' ', $presets::AppearanceLabel) }}">{{ __('gcego-panels::messages.accent') }}</p>
            <div role="group" aria-labelledby="{{ $shell->id('appearance-accent') }}" class="{{ implode(' ', $presets::AccentSwatches) }}">
                <button type="button" x-on:click="setAccent(null)" data-gcego-accent-swatch="default" aria-pressed="false" x-bind:aria-pressed="(accent === null).toString()" title="{{ __('gcego-panels::messages.accent_default') }}" class="{{ implode(' ', $presets::AccentSwatch) }}">
                    <span class="sr-only">{{ __('gcego-panels::messages.accent_default') }}</span>
                </button>
                @foreach ($accents as $accent)
                    <button type="button" x-on:click="setAccent('{{ $accent->name }}')" data-gcego-accent-swatch="{{ $accent->name }}" aria-pressed="false" x-bind:aria-pressed="(accent === '{{ $accent->name }}').toString()" title="{{ $accent->label }}" class="{{ implode(' ', $presets::AccentSwatch) }}">
                        <span class="sr-only">{{ $accent->label }}</span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>
@endif
