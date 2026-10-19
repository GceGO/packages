<header class="{{ $styles('header') }}">
    <div class="flex min-w-0 flex-col gap-0.5">
        <h3 id="{{ $headingId }}" class="{{ $styles('title') }}">{{ __($title) }}</h3>
        @if ($description)
            <p class="{{ $styles('description') }}">{{ __($description) }}</p>
        @endif
    </div>
    @if ($icon)
        <span class="{{ implode(' ', $presets::IconBadge) }} {{ $presets::Tones[$tone ?? 'default']['bg'] ?? '' }} {{ $presets::Tones[$tone ?? 'default']['text'] ?? '' }}" aria-hidden="true">{{ $icons->svg($icon, 'size-5') }}</span>
    @endif
</header>
