@if ($cell['text'] === null)
    <span class="text-muted-foreground">{{ $column->placeholder() }}</span>
@elseif ($cell['href'] === null)
    <span @if ($cell['title']) title="{{ $cell['title'] }}" @endif>{{ $cell['text'] }}</span>
@else
    <a href="{{ $cell['href'] }}" class="{{ implode(' ', $presets::Link) }}" @if ($cell['target']) target="{{ $cell['target'] }}" rel="{{ $cell['rel'] }}" @endif @if ($cell['title']) title="{{ $cell['title'] }}" @endif>
        {{ $cell['text'] }}@if ($cell['target'])<span class="sr-only"> {{ __('gcego-tables::messages.new_tab') }}</span>@endif
    </a>
@endif
