@unless ($skip)
    <section class="{{ $styles('root') }}" aria-labelledby="{{ $headingId }}" data-gcego-widget="{{ $widget->widgetName() }}">
        @include('gcego-widgets::widgets.header')
        @if ($error || $empty)
            @include('gcego-widgets::widgets.states')
        @else
            <ol class="flex flex-col divide-y divide-gcego-border">
                @foreach ($items as $item)
                    <li class="flex gap-3 py-2.5" @if ($item['key']) data-gcego-item="{{ $item['key'] }}" @endif>
                        <span class="{{ implode(' ', $presets::IconBadge) }} size-8 {{ $presets::Tones[$item['tone']]['bg'] }} {{ $presets::Tones[$item['tone']]['text'] }}" aria-hidden="true">{{ $icons->svg($item['icon'] ?? 'widgets.activity', 'size-4') }}</span>
                        <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                            <p class="flex flex-wrap items-center gap-2 text-sm font-medium [overflow-wrap:anywhere]">
                                @if ($item['url'])<a href="{{ $item['url'] }}" class="{{ implode(' ', $presets::Link) }}">{{ $item['title'] }}</a>@else{{ $item['title'] }}@endif
                                @if ($item['badge'])<span class="{{ implode(' ', $tablePresets::Badge) }} {{ implode(' ', $tablePresets::Variants['neutral']) }}">{{ $item['badge'] }}</span>@endif
                            </p>
                            @if ($item['description'])<p class="text-xs text-gcego-text-muted [overflow-wrap:anywhere]">{{ $item['description'] }}</p>@endif
                            <p class="text-xs text-gcego-text-muted">
                                @if ($item['actor']){{ $item['actor'] }}@endif
                                @if ($item['actor'] && $item['at']) · @endif
                                @if ($item['at'])<time datetime="{{ $item['at']->toIso8601String() }}" title="{{ $item['at']->locale(app()->getLocale())->isoFormat('lll') }}">{{ $item['at']->locale(app()->getLocale())->diffForHumans() }}</time>@endif
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>
            @if (($hasMore ?? false) || ($hasPrevious ?? false))
                <nav class="{{ $styles('footer') }}" aria-label="{{ __('gcego-widgets::messages.pages', ['widget' => __($title)]) }}">
                    <button type="button" wire:click="newerPage" @disabled(! ($hasPrevious ?? false)) class="{{ implode(' ', $presets::Button) }}">{{ __('gcego-widgets::messages.newer') }}</button>
                    <button type="button" wire:click="olderPage" @disabled(! ($hasMore ?? false)) class="{{ implode(' ', $presets::Button) }}">{{ __('gcego-widgets::messages.older') }}</button>
                </nav>
            @endif
        @endif
    </section>
@endunless
