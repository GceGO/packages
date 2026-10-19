@if (($cell['html'] ?? null) !== null)
    <div class="{{ implode(' ', $presets::Prose) }}">{{ $cell['html'] }}</div>
@elseif ($cell['text'] === null)
    @include('gcego-infolists::entries.placeholder')
@else
    <div class="flex items-start gap-1.5" @if ($cell['copy']) x-data="{ copied: false }" @endif>
        {{-- The value touches the tags: whitespace-pre-line would show surrounding newlines as blank lines. --}}
        <span @class(['whitespace-pre-line' => $cell['multiline'] ?? false, 'font-mono' => $cell['mono'] ?? false]) @if ($cell['title']) title="{{ $cell['title'] }}" @endif>@isset($cell['datetime'])<time datetime="{{ $cell['datetime'] }}">{{ $cell['text'] }}</time>@else{{ $cell['text'] }}@endisset</span>
        @if ($cell['copy'])
            @include('gcego-infolists::entries.copy', ['copyValue' => $cell['full'] ?? $cell['text']])
        @endif
    </div>
@endif
