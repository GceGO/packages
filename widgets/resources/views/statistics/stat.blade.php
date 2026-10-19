@unless ($skip)
    @php($tone = $tone ?? 'default')
    <article class="{{ $styles('root') }}" aria-labelledby="{{ $headingId }}" data-gcego-widget="{{ $widget->widgetName() }}">
        @include('gcego-widgets::widgets.header')
        @if ($error || $empty)
            @include('gcego-widgets::widgets.states')
        @else
            <div class="{{ $styles('body') }}">
                <p class="{{ $styles('value') }}"><data value="{{ $raw }}">{{ $value }}</data></p>
                @if ($trend)
                    <p class="{{ $styles('trend') }} {{ $presets::Tones[$trend->tone()]['text'] }}" data-gcego-trend="{{ $trend->direction }}" data-gcego-sentiment="{{ $trend->sentiment }}">
                        <span aria-hidden="true">{{ $icons->svg(match ($trend->direction) { 'up' => 'widgets.increase', 'down' => 'widgets.decrease', default => 'widgets.unchanged' }, 'size-3.5') }}</span>
                        <span>{{ $trendText }}</span>
                    </p>
                @endif
            </div>
            @if ($url)
                <footer class="{{ $styles('footer') }}">
                    <a href="{{ $url }}" class="{{ implode(' ', $presets::Link) }}">{{ __('gcego-widgets::messages.details') }}<span class="sr-only"> {{ __('gcego-widgets::messages.about', ['widget' => __($title)]) }}</span></a>
                </footer>
            @endif
        @endif
    </article>
@endunless
