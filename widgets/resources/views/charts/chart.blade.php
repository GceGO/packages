@unless ($skip)
    @php($chartId = $frame->id($context->path().'-chart'))
    <figure class="{{ $styles('root') }}" aria-labelledby="{{ $headingId }}" data-gcego-widget="{{ $widget->widgetName() }}">
        @include('gcego-widgets::widgets.header')
        @if ($error || $empty)
            @include('gcego-widgets::widgets.states')
        @else
            @php($json = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR))
            {{-- Re-keyed when the data changes: Alpine destroys the old chart and builds a new one (no duplicate instances). --}}
            <div class="{{ $styles('chart') }} {{ $presets::ChartHeights[$widget->layout()['height']] ?? 'h-64' }}" wire:key="{{ $chartId }}-{{ md5($json) }}" x-data="gcegoChart" data-gcego-chart="{{ $module }}">
                <canvas role="img" aria-label="{{ $summary }}" aria-describedby="{{ $chartId }}-summary"></canvas>
                <script type="application/json" data-gcego-chart-config>{!! $json !!}</script>
            </div>
            <figcaption id="{{ $chartId }}-summary" class="{{ $styles('description') }}">{{ $summary }}</figcaption>
            <details class="text-xs">
                <summary class="cursor-pointer text-gcego-text-muted focus-visible:outline-2 focus-visible:outline-gcego-ring">{{ __('gcego-widgets::messages.show_data') }}</summary>
                <div class="mt-2 max-h-64 overflow-auto">
                    <table class="{{ implode(' ', $presets::DataTable) }}">
                        <caption class="sr-only">{{ __($title) }}</caption>
                        <thead><tr><th scope="col" class="py-1 pr-3">{{ __('gcego-widgets::messages.label') }}</th>@foreach ($data->datasets as $dataset)<th scope="col" class="py-1 pr-3">{{ $dataset->label }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr class="border-t border-gcego-border"><th scope="row" class="py-1 pr-3 font-normal">{{ $row['label'] }}</th>@foreach ($row['values'] as $value)<td class="py-1 pr-3 tabular-nums">{{ $value }}</td>@endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif
    </figure>
@endunless
