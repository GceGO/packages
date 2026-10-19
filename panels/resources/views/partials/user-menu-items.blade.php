{{-- gcego-panels::partials.user-menu-items — who is signed in (and the tenant), then the account links and sign out
     (POST + CSRF). Shared by the top bar's user menu and the sidebar's profile. --}}
@php($presets = \Gcego\Panels\Presets\PanelPresets::class)
<p class="{{ implode(' ', $presets::MenuHeader) }}">
    {{ __('gcego-panels::messages.signed_in_as', ['name' => $shell->userName]) }}
    @if ($shell->tenantName !== null)
        <span class="block">{{ __('gcego-panels::messages.tenant', ['tenant' => $shell->tenantName]) }}</span>
    @endif
</p>
@include('gcego-panels::partials.appearance', ['shell' => $shell])
<ul role="list">
    @foreach ($shell->userMenu as $link)
        <li>
            @if ($link->post)
                <form method="POST" action="{{ $link->url }}">
                    @csrf
                    <button type="submit" class="{{ implode(' ', $presets::MenuItem) }}">
                        @if ($link->icon !== null){{ $shell->svg($link->icon) }}@endif
                        {{ $link->label }}
                    </button>
                </form>
            @else
                <a href="{{ $link->url }}" class="{{ implode(' ', $presets::MenuItem) }}">
                    @if ($link->icon !== null){{ $shell->svg($link->icon) }}@endif
                    {{ $link->label }}
                </a>
            @endif
        </li>
    @endforeach
</ul>
