{{-- gcego-panels::partials.user-menu — the signed-in user, the tenant, account links and sign out (POST + CSRF). --}}
@php($presets = \Gcego\Panels\Presets\PanelPresets::class)
<details x-data x-on:click.outside="$el.removeAttribute('open')" x-on:keydown.escape="$el.removeAttribute('open'); $el.querySelector('summary').focus()" class="{{ $shell->classes(\Gcego\Panels\Panel::UserMenuSlot, $presets::Menu) }}">
    <summary class="{{ implode(' ', $presets::MenuTrigger) }}">
        {{ $shell->svg('panels.user-menu') }}
        <span class="hidden truncate sm:inline">{{ $shell->userName }}</span>
        <span class="sr-only">{{ __('gcego-panels::messages.user_menu') }}</span>
    </summary>
    <div class="{{ implode(' ', [...$presets::MenuPanel, ...$presets::MenuPlacement['end']]) }}">
        @include('gcego-panels::partials.user-menu-items', ['shell' => $shell])
    </div>
</details>
