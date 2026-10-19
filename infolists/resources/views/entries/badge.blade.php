@if ($cell['items'] === [])
    @include('gcego-infolists::entries.placeholder')
@else
    <span class="flex flex-wrap gap-1.5">
        @foreach ($cell['items'] as $item)
            <span class="{{ implode(' ', $tablePresets::Badge) }} {{ implode(' ', $tablePresets::Variants[$item['variant']]) }}" data-gcego-badge="{{ $item['variant'] }}">{{ $icons->svg($item['icon'], 'size-3.5') }}{{ $item['text'] }}</span>
        @endforeach
    </span>
@endif
