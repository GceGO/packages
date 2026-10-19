@if ($cell['text'] === null)
    <span class="text-muted-foreground">{{ $column->placeholder() }}</span>
@else
    <span class="{{ implode(' ', $presets::Badge) }} {{ implode(' ', $presets::Variants[$cell['variant']]) }}" data-gcego-badge="{{ $cell['variant'] }}" @if ($cell['title']) title="{{ $cell['title'] }}" @endif>
        {{ $icons->svg($cell['icon'], 'size-3.5') }}{{ $cell['text'] }}
    </span>
@endif
