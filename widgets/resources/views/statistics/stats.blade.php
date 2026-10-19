@unless ($skip)
    <section aria-labelledby="{{ $headingId }}" data-gcego-widget="{{ $widget->widgetName() }}" class="flex flex-col gap-3">
        <h2 id="{{ $headingId }}" @class(['sr-only' => $widget->get('label') === null, implode(' ', $presets::SectionTitle) => $widget->get('label') !== null])>{{ __($title) }}</h2>
        @if ($error)
            <div class="{{ implode(' ', $presets::Card) }}">@include('gcego-widgets::widgets.states')</div>
        @else
            <div class="{{ $widget->grid() }}">@foreach ($stats as $html){!! $html !!}@endforeach</div>
        @endif
    </section>
@endunless
