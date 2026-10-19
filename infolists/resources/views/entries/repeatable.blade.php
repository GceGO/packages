@if ($items === [])
    @include('gcego-infolists::entries.placeholder')
@else
    <ol class="{{ implode(' ', $presets::Repeatable) }}" aria-label="{{ __($entry->label()) }}">
        @foreach ($items as $item)
            <li class="{{ implode(' ', $presets::RepeatableItem) }}" data-gcego-item="{{ $item['key'] }}">
                @php($groups = $item['groups'])
                @php($grid = $presets::Grids[$entry->columns()] ?? $presets::Grids[1])
                @include('gcego-infolists::layouts.groups')
            </li>
        @endforeach
    </ol>
@endif
