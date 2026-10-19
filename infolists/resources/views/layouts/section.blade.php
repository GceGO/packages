@unless ($skip)
    @php
        $collapsible = $layout->get('collapsible') === true;
        $headingId = $frame->id('section-'.$context->path());
        $bodyId = $frame->id('section-body-'.$context->path());
    @endphp
    <section class="{{ $styles('root') }}" @if ($heading) aria-labelledby="{{ $headingId }}" @endif @if ($collapsible) x-data="{ open: {{ $layout->get('collapsed') === true ? 'false' : 'true' }} }" @endif data-gcego-section>
        @if ($heading || $description)
            <header class="{{ $styles('header') }}">
                <div class="flex min-w-0 flex-col gap-0.5">
                    @if ($heading)
                        <h3 id="{{ $headingId }}" class="{{ implode(' ', $presets::SectionTitle) }}">{{ $icons->svg($layout->get('icon'), 'size-5 text-gcego-text-muted') }}{{ __($heading) }}</h3>
                    @endif
                    @if ($description)
                        <p class="{{ $styles('description') }}">{{ __($description) }}</p>
                    @endif
                </div>
                @if ($collapsible)
                    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()" aria-expanded="{{ $layout->get('collapsed') === true ? 'false' : 'true' }}" aria-controls="{{ $bodyId }}" class="{{ implode(' ', $presets::CollapseButton) }}">
                        <span class="sr-only">{{ __('gcego-infolists::messages.toggle', ['section' => __($heading ?? '')]) }}</span>
                        <span x-bind:class="open ? 'rotate-180' : ''" class="transition-transform">{{ $icons->svg('layouts.expand', 'size-5') }}</span>
                    </button>
                @endif
            </header>
        @endif
        <div id="{{ $bodyId }}" class="{{ $styles('body') }}" @if ($collapsible) x-show="open" @if ($layout->get('collapsed') === true) x-cloak @endif @endif>
            @include('gcego-infolists::layouts.groups')
        </div>
    </section>
@endunless
