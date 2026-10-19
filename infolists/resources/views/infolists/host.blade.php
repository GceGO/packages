<div data-gcego-infolist-host="{{ $definition }}">
    @if ($content === null)
        <div class="flex flex-col items-center justify-center gap-2 rounded-gcego-lg border border-gcego-border bg-gcego-surface px-6 py-12 text-center text-gcego-text-muted" role="status" data-gcego-infolist-unavailable>
            <p class="font-semibold text-gcego-text">{{ __('gcego-infolists::messages.unavailable') }}</p>
            <p class="text-sm">{{ __('gcego-infolists::messages.unavailable_hint') }}</p>
        </div>
    @else
        {{ $content }}
    @endif
</div>
