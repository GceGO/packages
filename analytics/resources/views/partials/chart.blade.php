{{-- gcego-analytics::partials.chart — rendered by gcego/widgets' chart script (Alpine "gcegoChart"), with a data table. --}}
@php($p = \Gcego\Analytics\Presets\AnalyticsPresets::class)
<figure class="{{ $styles->classes('chart', $p::Chart) }}" aria-labelledby="gcego-chart-{{ $chart->name }}">
    <h3 id="gcego-chart-{{ $chart->name }}" class="{{ implode(' ', $p::ChartTitle) }}">{{ $chart->title }}</h3>
    @if ($chart->error !== null)
        <p class="{{ $styles->classes('error', $p::Error) }}" role="alert">{{ $chart->error }}</p>
    @elseif ($chart->empty)
        <p class="{{ $styles->classes('empty', $p::Empty) }}">{{ __('gcego-analytics::messages.empty') }}</p>
    @else
        @php($json = json_encode($chart->config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR))
        {{-- Re-keyed when the data changes: Alpine destroys the old chart (no duplicate instances or listeners). --}}
        <div class="{{ implode(' ', $p::ChartArea) }}" wire:key="chart-{{ $chart->name }}-{{ md5($json) }}" x-data="gcegoChart" data-gcego-chart="{{ $chart->module }}">
            <canvas role="img" aria-label="{{ $chart->summary }}" aria-describedby="gcego-chart-{{ $chart->name }}-summary"></canvas>
            <script type="application/json" data-gcego-chart-config>{!! $json !!}</script>
        </div>
        <figcaption id="gcego-chart-{{ $chart->name }}-summary" class="{{ implode(' ', $p::Description) }}">{{ $chart->summary }}</figcaption>
        @if (count($chart->series) > 1)
            <ul class="{{ $styles->classes('chart_legend', $p::ChartLegend) }}" aria-label="{{ __('gcego-analytics::messages.label') }}">
                @foreach ($chart->series as $series)
                    <li>{{ $series }}</li>
                @endforeach
            </ul>
        @endif
        <details class="text-xs">
            <summary class="cursor-pointer text-gcego-text-muted focus-visible:outline-2 focus-visible:outline-gcego-ring">{{ __('gcego-analytics::messages.show_data') }}</summary>
            <div class="mt-2 max-h-64 overflow-auto">
                <table class="{{ implode(' ', $p::DataTable) }}">
                    <caption class="sr-only">{{ $chart->title }}</caption>
                    <thead><tr><th scope="col" class="py-1 pr-3">{{ __('gcego-analytics::messages.label') }}</th>@foreach ($chart->series as $series)<th scope="col" class="py-1 pr-3">{{ $series }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($chart->rows as $row)
                            <tr class="border-t border-gcego-border"><th scope="row" class="py-1 pr-3 font-normal">{{ $row['label'] }}</th>@foreach ($row['values'] as $value)<td class="py-1 pr-3 tabular-nums">{{ $value }}</td>@endforeach</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif
</figure>
