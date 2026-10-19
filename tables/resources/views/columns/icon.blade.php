@if ($cell['icon'] === null)
    <span class="text-muted-foreground">{{ $cell['text'] ?? $column->placeholder() }}</span>
@else
    <span class="inline-flex {{ implode(' ', $presets::IconTones[$cell['tone']]) }}" @if ($cell['text']) title="{{ $cell['text'] }}" @endif>
        {{ $icons->svg($cell['icon'], 'size-5') }}<span class="sr-only">{{ $cell['text'] }}</span>
    </span>
@endif
