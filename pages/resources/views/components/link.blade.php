{{-- gcego-pages::components.link --}}
<a href="{{ $url }}" class="{{ $class }}"@if ($navigate) wire:navigate @endif>@if ($icon !== null)<span aria-hidden="true" class="contents">{{ $icon }}</span>@endif<span>{{ $label }}</span></a>
