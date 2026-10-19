@unless ($skip)
    <section class="{{ $styles('root') }}" aria-labelledby="{{ $headingId }}" data-gcego-widget="{{ $widget->widgetName() }}">
        @include('gcego-widgets::widgets.header')
        @if ($error || $empty)
            @include('gcego-widgets::widgets.states')
        @else
            <div class="{{ $styles('body') }}">{{ $details }}</div>
        @endif
    </section>
@endunless
