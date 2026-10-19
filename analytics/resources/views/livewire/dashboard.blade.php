{{-- gcego-analytics::livewire.dashboard --}}
@php($p = \Gcego\Analytics\Presets\AnalyticsPresets::class)
<div class="{{ $styles->classes('wrapper', $p::Wrapper) }}">
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="{{ implode(' ', $p::Title) }}">{{ $view->title }}</h1>
            @if ($view->description)
                <p class="{{ implode(' ', $p::Description) }}">{{ $view->description }}</p>
            @endif
        </div>
        <div class="{{ $styles->classes('toolbar', $p::Toolbar) }}" role="group" aria-label="{{ __('gcego-analytics::messages.filters') }}" x-data="{ open: false }">
            @include('gcego-analytics::partials.period', ['default' => $definition->defaultRange()->value, 'range' => $range])
            <label class="flex flex-col gap-1 {{ implode(' ', $p::FilterLabel) }}">
                {{ __('gcego-analytics::messages.compare') }}
                <select wire:model.live="comparison" class="{{ implode(' ', $p::Control) }}">
                    <option value="">{{ __('gcego-analytics::messages.comparisons.'.$definition->comparison()->value) }}</option>
                    @foreach (array_diff(['none', 'previous_period', 'previous_year'], [$definition->comparison()->value]) as $mode)
                        <option value="{{ $mode }}">{{ __('gcego-analytics::messages.comparisons.'.$mode) }}</option>
                    @endforeach
                </select>
            </label>
            @if ($view->filters !== [])
                <div class="relative">
                    <button type="button" class="{{ implode(' ', $p::Button) }}" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()" aria-controls="gcego-dashboard-filters-{{ $this->getId() }}" aria-expanded="false">{{ __('gcego-analytics::messages.show_filters') }}</button>
                    <div id="gcego-dashboard-filters-{{ $this->getId() }}" class="{{ $styles->classes('filters', $p::Panel) }}" x-show="open" x-cloak x-on:keydown.escape.window="open = false" x-on:click.outside="open = false">
                        @foreach ($view->filters as $filter)
                            <label class="mb-2 flex flex-col gap-1 {{ implode(' ', $p::FilterLabel) }}">
                                {{ $filter['label'] }}
                                <select wire:model.live="filters.{{ $filter['name'] }}" class="{{ implode(' ', $p::Control) }}">
                                    <option value="">{{ __('gcego-analytics::messages.all') }}</option>
                                    @foreach ($filter['options'] as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endforeach
                        <button type="button" wire:click="resetFilters" class="{{ implode(' ', $p::Link) }}">{{ __('gcego-analytics::messages.reset') }}</button>
                    </div>
                </div>
            @endif
            <button type="button" wire:click="refreshData" wire:loading.attr="disabled" class="{{ implode(' ', $p::Button) }}">{{ __('gcego-analytics::messages.refresh') }}</button>
        </div>
    </header>

    <div role="status" aria-live="polite" class="text-sm text-gcego-text-muted">
        <span wire:loading>{{ __('gcego-analytics::messages.loading') }}</span>
        @if ($message)<span wire:loading.remove>{{ $message }}</span>@endif
    </div>
    @error('analytics')
        <p class="{{ $styles->classes('error', $p::Notice) }}" role="alert">{{ $message }}</p>
    @enderror

    <p class="{{ implode(' ', $p::Description) }}">{{ __('gcego-analytics::messages.period') }}: <time datetime="{{ $view->state->range->start->toDateString() }}">{{ $view->state->range->start->isoFormat('ll') }}</time> – <time datetime="{{ $view->state->range->end()->toDateString() }}">{{ $view->state->range->end()->isoFormat('ll') }}</time></p>

    @if ($view->kpis !== [])
        <section class="{{ implode(' ', $p::Grid) }}" aria-label="{{ $view->title }}">
            @foreach ($view->kpis as $kpi)
                <article class="{{ $styles->classes('metric', $p::Metric) }}" aria-labelledby="gcego-kpi-{{ $kpi['name'] }}" wire:key="kpi-{{ $kpi['name'] }}">
                    @if ($kpi['result'] === null)
                        <h2 id="gcego-kpi-{{ $kpi['name'] }}" class="{{ implode(' ', $p::MetricLabel) }}">{{ $kpi['name'] }}</h2>
                        <p class="{{ $styles->classes('error', $p::Error) }}" role="alert">{{ $kpi['error'] }}</p>
                    @else
                        @php($result = $kpi['result'])
                        <h2 id="gcego-kpi-{{ $kpi['name'] }}" class="{{ implode(' ', $p::MetricLabel) }}">{{ $result->label }}</h2>
                        <p class="{{ $styles->classes('metric_value', $p::MetricValue) }}"><data value="{{ $result->value ?? '' }}">{{ $result->formatted }}</data></p>
                        @if ($result->comparison !== null)
                            @php($c = $result->comparison)
                            <p class="{{ $styles->classes('metric_trend', $p::MetricTrend) }} {{ $p::Trends[$c->direction] }}" data-gcego-trend="{{ $c->direction }}">
                                <span aria-hidden="true">{{ ['up' => '↑', 'down' => '↓', 'flat' => '→', 'unknown' => '·'][$c->direction] }}</span>
                                <span>{{ __('gcego-analytics::messages.trend.'.$c->direction, ['change' => $c->percentChange === null ? $result->format->format($c->change === null ? null : abs($c->change), $result->precision, unit: $result->unit) : \Gcego\Analytics\Support\Format::Number->format(abs($c->percentChange), 1).' %', 'previous' => $result->format->format($c->previous, $result->precision, unit: $result->unit)]) }}</span>
                            </p>
                        @endif
                    @endif
                </article>
            @endforeach
        </section>
    @endif

    @if ($view->charts !== [])
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($view->charts as $chart)
                @include('gcego-analytics::partials.chart', ['chart' => $chart, 'styles' => $styles])
            @endforeach
        </div>
    @endif

    @foreach ($view->reports as $section)
        <section class="{{ $styles->classes('report', $p::Report) }}" aria-labelledby="gcego-section-{{ $section['name'] }}" wire:key="report-{{ $section['name'] }}">
            <header class="{{ implode(' ', $p::Header) }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="gcego-section-{{ $section['name'] }}" class="{{ implode(' ', $p::ChartTitle) }}">{{ $section['result']?->title ?? $section['name'] }}</h2>
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($reportLink)
                            <a href="{{ str_replace(':report', $section['name'], $reportLink) }}" class="{{ implode(' ', $p::Link) }}">{{ __('gcego-analytics::messages.view_report') }}</a>
                        @endif
                    </div>
                </div>
            </header>
            @if ($section['result'] === null)
                <p class="{{ $styles->classes('error', $p::Error) }}" role="alert">{{ $section['error'] }}</p>
            @else
                @include('gcego-analytics::partials.table', ['result' => $section['result'], 'styles' => $styles, 'drill' => []])
            @endif
        </section>
    @endforeach
</div>
