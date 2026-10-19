@foreach ($groups as $group)
    @if ($group['type'] === 'entries')
        <dl class="{{ $grid }}">@foreach ($group['html'] as $html){!! $html !!}@endforeach</dl>
    @else
        @foreach ($group['html'] as $html){!! $html !!}@endforeach
    @endif
@endforeach
