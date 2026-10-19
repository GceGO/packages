@if ($cell['state'] === null)
    <span class="text-muted-foreground">{{ $column->placeholder() }}</span>
@else
    <span class="inline-flex items-center gap-1.5 {{ implode(' ', $presets::IconTones[$cell['tone']]) }}" data-gcego-boolean="{{ $cell['state'] ? 'true' : 'false' }}">
        {{ $icons->svg($cell['icon'], 'size-5') }}
        <span @class(['text-sm text-foreground' => $cell['show'], 'sr-only' => ! $cell['show']])>{{ $cell['text'] }}</span>
    </span>
@endif
