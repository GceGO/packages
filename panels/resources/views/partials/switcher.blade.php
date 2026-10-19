{{-- gcego-panels::partials.switcher — the panels this user may open (each checks access again when opened).
     A native disclosure: works without JavaScript; Alpine closes it on Escape or an outside click. --}}
@php($presets = \Gcego\Panels\Presets\PanelPresets::class)
<details x-data x-on:click.outside="$el.removeAttribute('open')" x-on:keydown.escape="$el.removeAttribute('open'); $el.querySelector('summary').focus()" class="{{ $shell->classes(\Gcego\Panels\Panel::SwitcherSlot, $presets::Menu) }}">
    <summary class="{{ implode(' ', $presets::MenuTrigger) }}">
        {{ $shell->svg('panels.switch-panel') }}
        <span class="{{ implode(' ', $presets::SwitcherText) }}">{{ $shell->panel->title() }}</span>
        <span class="sr-only">{{ __('gcego-panels::messages.switch') }}</span>
        <span class="{{ implode(' ', $presets::SwitcherText) }}">{{ $shell->svg('panels.menu-toggle', 'size-4') }}</span>
    </summary>
    <nav aria-label="{{ __('gcego-panels::messages.panels') }}" class="{{ implode(' ', [...$presets::MenuPanel, ...$presets::MenuPlacement[$align ?? 'end']]) }}">
        <ul role="list">
            @foreach ($shell->switcher as $link)
                <li>
                    <a href="{{ $link->url }}" @if ($link->current) aria-current="page" @endif class="{{ $link->current ? $shell->merge($presets::MenuItem, $presets::MenuItemCurrent) : implode(' ', $presets::MenuItem) }}">
                        <span class="min-w-0">
                            <span class="block truncate">{{ $link->title }}</span>
                            @if ($link->description !== null)
                                <span class="{{ implode(' ', $presets::Tagline) }}">{{ $link->description }}</span>
                            @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
</details>
