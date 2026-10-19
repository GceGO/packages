{{-- gcego-media::entries.preview — read-only previews. Players use the authorized media route; nothing uploaded is
     embedded as markup. --}}
@php($presets = \Gcego\Media\Presets\MediaPresets::class)
<ul role="list" class="flex flex-col gap-4">
    @foreach ($cell['items'] as $item)
        <li class="flex flex-col gap-2">
            @if ($item['kind'] === 'image')
                <img src="{{ $item['src'] }}" alt="{{ $item['alt'] }}" loading="lazy" decoding="async" @if ($item['width'] !== null) width="{{ $item['width'] }}" height="{{ $item['height'] }}" @endif class="{{ implode(' ', $presets::Preview) }}">
            @elseif ($item['kind'] === 'video' && $cell['players'])
                <video controls preload="metadata" class="{{ implode(' ', $presets::Preview) }}" aria-label="{{ $item['name'] }}"><source src="{{ $item['src'] }}" type="{{ $item['mime'] }}"></video>
            @elseif ($item['kind'] === 'audio' && $cell['players'])
                <audio controls preload="metadata" class="w-full" aria-label="{{ $item['name'] }}"><source src="{{ $item['src'] }}" type="{{ $item['mime'] }}"></audio>
            @elseif ($item['kind'] === 'pdf' && $cell['players'])
                <iframe src="{{ $item['src'] }}" title="{{ $item['name'] }}" loading="lazy" sandbox="allow-same-origin allow-downloads" class="h-96 w-full rounded-gcego border border-gcego-border"></iframe>
            @endif
            <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                <span class="min-w-0 truncate font-medium text-gcego-text">{{ $item['name'] }}</span>
                <span class="text-xs text-gcego-text-muted">{{ $item['mime'] }} · {{ $item['size'] }}@if ($item['width'] !== null) · {{ $item['width'] }}×{{ $item['height'] }}@endif @if ($item['duration'] !== null) · {{ $item['duration'] }}@endif</span>
                <a href="{{ $item['download'] }}" class="{{ implode(' ', $presets::Link) }}">{{ __('gcego-media::messages.download') }}<span class="sr-only">: {{ $item['name'] }}</span></a>
            </div>
        </li>
    @endforeach
</ul>
