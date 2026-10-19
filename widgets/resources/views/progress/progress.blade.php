@unless ($skip)
    <article class="{{ $styles('root') }}" aria-labelledby="{{ $headingId }}" data-gcego-widget="{{ $widget->widgetName() }}">
        @include('gcego-widgets::widgets.header')
        @if ($error || $empty)
            @include('gcego-widgets::widgets.states')
        @else
            @php($text = $percent === null ? $value : __('gcego-widgets::messages.progress_text', ['percent' => $percent.'%', 'value' => $value, 'maximum' => $maximum]))
            <div class="{{ $styles('body') }}">
                <p class="flex items-baseline justify-between gap-2"><span class="{{ $styles('value') }}">{{ $percent === null ? $value : $percent.'%' }}</span><span class="text-xs text-gcego-text-muted">{{ $value }} / {{ $maximum }}</span></p>
                <div class="{{ implode(' ', $presets::ProgressTrack) }}" role="progressbar" aria-labelledby="{{ $headingId }}" aria-valuemin="{{ $min }}" aria-valuemax="{{ $max }}" aria-valuenow="{{ $now }}" aria-valuetext="{{ $text }}">
                    <div class="h-full rounded-full {{ $presets::Tones[$tone]['bar'] ?? 'bg-gcego-primary' }}" style="width: {{ $clamped ?? 0 }}%"></div>
                </div>
            </div>
        @endif
    </article>
@endunless
