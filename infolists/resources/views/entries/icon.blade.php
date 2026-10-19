@if ($cell['icon'] === null && $cell['text'] === null)
    @include('gcego-infolists::entries.placeholder')
@else
    <span class="inline-flex items-center gap-2">
        @if ($cell['icon'] !== null)<span class="{{ implode(' ', $tablePresets::IconTones[$cell['tone']]) }}">{{ $icons->svg($cell['icon'], $cell['size']) }}</span>@endif
        <span @class(['sr-only' => ! $cell['show'] && $cell['icon'] !== null])>{{ $cell['text'] }}</span>
    </span>
@endif
