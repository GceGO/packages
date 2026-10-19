@unless ($skip)
    @php($statusTone = match ($status) { 'achieved', 'within' => 'success', 'behind' => 'warning', 'exceeded' => 'danger', default => 'default' })
    <article class="{{ $styles('root') }}" aria-labelledby="{{ $headingId }}" data-gcego-widget="{{ $widget->widgetName() }}" data-gcego-goal="{{ $status }}">
        @include('gcego-widgets::widgets.header')
        @if ($error || $empty)
            @include('gcego-widgets::widgets.states')
        @else
            <div class="{{ $styles('body') }}">
                <p class="flex items-baseline justify-between gap-2">
                    <span class="{{ $styles('value') }}">{{ $value }}</span>
                    <span class="{{ implode(' ', $tablePresets::Badge) }} {{ implode(' ', $tablePresets::Variants[$statusTone === 'default' ? 'info' : $statusTone]) }}">{{ __('gcego-widgets::messages.goal_'.$status) }}</span>
                </p>
                <div class="{{ implode(' ', $presets::ProgressTrack) }}" role="progressbar" aria-labelledby="{{ $headingId }}" aria-valuemin="0" aria-valuemax="{{ $max }}" aria-valuenow="{{ $now }}" aria-valuetext="{{ __('gcego-widgets::messages.goal_text', ['value' => $value, 'target' => $target, 'percent' => ($percent ?? 0).'%']) }}">
                    <div class="h-full rounded-full {{ $presets::Tones[$statusTone]['bar'] }}" style="width: {{ $clamped ?? 0 }}%"></div>
                </div>
                <p class="text-xs text-gcego-text-muted">
                    {{ __('gcego-widgets::messages.goal_target', ['target' => $target]) }}
                    @if ($remaining !== null && $remaining > 0) · {{ __('gcego-widgets::messages.goal_remaining', ['remaining' => $remainingText]) }}@endif
                    @if ($deadline) · {{ __('gcego-widgets::messages.goal_deadline') }} <time datetime="{{ $deadline->format('Y-m-d') }}">{{ $deadline->locale(app()->getLocale())->isoFormat('ll') }}</time>@endif
                </p>
            </div>
        @endif
    </article>
@endunless
