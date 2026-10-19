{{-- gcego-panels::partials.avatar — the user's picture (a root-relative or https URL, checked by the factory), else their initials. --}}
@php($presets = \Gcego\Panels\Presets\PanelPresets::class)
<span aria-hidden="true" class="{{ implode(' ', $presets::Avatar) }}">
    @if ($shell->userAvatar !== null)
        <img src="{{ $shell->userAvatar }}" alt="" class="{{ implode(' ', $presets::AvatarImage) }}">
    @else
        {{ $shell->userInitials() }}
    @endif
</span>
