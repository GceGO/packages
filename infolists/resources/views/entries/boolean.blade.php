@if ($cell['state'] === null)
    @include('gcego-infolists::entries.placeholder')
@else
    <span class="inline-flex items-center gap-1.5" data-gcego-boolean="{{ $cell['state'] ? 'true' : 'false' }}">
        <span class="{{ implode(' ', $tablePresets::IconTones[$cell['tone']]) }}">{{ $icons->svg($cell['icon'], 'size-5') }}</span>
        <span>{{ $cell['text'] }}</span>
    </span>
@endif
