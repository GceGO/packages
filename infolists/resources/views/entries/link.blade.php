@if ($cell['text'] === null)
    @include('gcego-infolists::entries.placeholder')
@else
    <span class="flex items-start gap-1.5" @if ($cell['copy']) x-data="{ copied: false }" @endif>
        @if ($cell['href'] === null)
            <span>{{ $cell['text'] }}</span>
        @else
            <a href="{{ $cell['href'] }}" class="{{ implode(' ', $presets::Link) }} inline-flex items-center gap-1" @if ($cell['target']) target="{{ $cell['target'] }}" rel="{{ $cell['rel'] }}" @endif>
                {{ $icons->svg($cell['icon'], 'size-4') }}{{ $cell['text'] }}@if ($cell['target'])<span class="sr-only"> {{ __('gcego-infolists::messages.new_tab') }}</span>@endif
            </a>
        @endif
        @if ($cell['copy'])
            @include('gcego-infolists::entries.copy', ['copyValue' => $cell['copyValue']])
        @endif
    </span>
@endif
