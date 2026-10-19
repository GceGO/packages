@unless ($skip)
    <section class="{{ $styles('root') }}" aria-labelledby="{{ $headingId }}" data-gcego-widget="{{ $widget->widgetName() }}">
        @include('gcego-widgets::widgets.header')
        @if ($error)
            @include('gcego-widgets::widgets.states')
        @else
            <div class="{{ $widget->heightClass() }} overflow-auto">
                @livewire('gcego-table', ['definition' => $widget->blueprint()], key($frame->id($context->path().'-table')))
            </div>
        @endif
    </section>
@endunless
