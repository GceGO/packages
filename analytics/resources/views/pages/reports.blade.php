{{-- gcego-analytics::pages.reports — the reports the viewer may open. --}}
@php($p = \Gcego\Analytics\Presets\AnalyticsPresets::class)
<div class="{{ implode(' ', $p::Wrapper) }}">
    <h1 class="{{ implode(' ', $p::Title) }}">{{ __('gcego-analytics::messages.reports') }}</h1>
    @if ($reports === [])
        <p class="{{ implode(' ', $p::Empty) }}">{{ __('gcego-analytics::messages.empty') }}</p>
    @else
        <ul role="list" class="{{ implode(' ', $p::Grid) }}">
            @foreach ($reports as $report)
                <li class="{{ implode(' ', $p::Metric) }}">
                    <a href="{{ $base }}/{{ $report['name'] }}" class="{{ implode(' ', $p::Link) }}">{{ $report['title'] }}</a>
                    @if ($report['description'])
                        <p class="{{ implode(' ', $p::Description) }}">{{ $report['description'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
