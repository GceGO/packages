{{-- gcego-panels::partials.profile — the signed-in user at the foot of the sidebar: picture (or initials), name and
     email, opening the user menu. In the icon rail only the picture stays, and the menu opens beside it. --}}
@php($presets = \Gcego\Panels\Presets\PanelPresets::class)
<details x-data x-on:click.outside="$el.removeAttribute('open')" x-on:keydown.escape="$el.removeAttribute('open'); $el.querySelector('summary').focus()" class="{{ $shell->classes(\Gcego\Panels\Panel::ProfileSlot, $presets::Menu) }}">
    <summary class="{{ implode(' ', $presets::ProfileTrigger) }}">
        @include('gcego-panels::partials.avatar', ['shell' => $shell])
        <span class="{{ implode(' ', $presets::ProfileText) }}">
            <span class="{{ implode(' ', $presets::ProfileName) }}">{{ $shell->userName }}</span>
            @if ($shell->userEmail !== null)
                <span class="{{ implode(' ', $presets::ProfileEmail) }}">{{ $shell->userEmail }}</span>
            @endif
        </span>
        {{ $shell->svg('panels.menu-toggle', implode(' ', $presets::ProfileChevron)) }}
        <span class="sr-only">{{ __('gcego-panels::messages.user_menu') }}</span>
    </summary>
    <div class="{{ implode(' ', [...$presets::MenuPanel, ...$presets::ProfilePlacement]) }}">
        @include('gcego-panels::partials.user-menu-items', ['shell' => $shell])
    </div>
</details>
