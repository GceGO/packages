@if ($cell['items'] === [])
    @include('gcego-infolists::entries.placeholder')
@else
    @switch($cell['style'])
        @case('badges')
            <ul class="flex flex-wrap gap-1.5">@foreach ($cell['items'] as $item)<li class="{{ implode(' ', $tablePresets::Badge) }} {{ implode(' ', $tablePresets::Variants['neutral']) }}">{{ $item }}</li>@endforeach</ul>
            @break
        @case('inline')
            <ul class="flex flex-wrap gap-x-1">@foreach ($cell['items'] as $item)<li>{{ $item }}@unless ($loop->last),@endunless</li>@endforeach</ul>
            @break
        @case('numbered')
            <ol class="list-decimal space-y-0.5 pl-5">@foreach ($cell['items'] as $item)<li>{{ $item }}</li>@endforeach</ol>
            @break
        @default
            <ul class="list-disc space-y-0.5 pl-5">@foreach ($cell['items'] as $item)<li>{{ $item }}</li>@endforeach</ul>
    @endswitch
    @if ($cell['more'] > 0)
        <p class="mt-1 text-xs text-gcego-text-muted">{{ __('gcego-infolists::messages.more', ['count' => $cell['more']]) }}</p>
    @endif
@endif
