@if ($cell['text'] === null)
    <span class="text-muted-foreground">{{ $column->placeholder() }}</span>
@else
    <div class="flex items-center gap-1.5" @if ($cell['copy'] ?? false) x-data="{ copied: false }" @endif>
        <span @if ($cell['title']) title="{{ $cell['title'] }}" @endif>
            @isset($cell['datetime'])
                <time datetime="{{ $cell['datetime'] }}">{{ $cell['text'] }}</time>
            @else
                {{ $cell['text'] }}
            @endisset
        </span>
        @if ($cell['copy'] ?? false)
            <button type="button" data-copy="{{ $cell['title'] ?? $cell['text'] }}" x-on:click="navigator.clipboard?.writeText($el.dataset.copy); copied = true; setTimeout(() => copied = false, 1500)" class="inline-flex size-6 items-center justify-center rounded-gcego-sm text-muted-foreground transition hover:bg-surface hover:text-foreground hover:shadow-xs hover:ring-1 hover:ring-border focus-visible:opacity-100 focus-visible:outline-2 focus-visible:outline-ring [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-hover/row:opacity-100" aria-label="{{ __('gcego-tables::messages.copy') }}">
                {{ $icons->svg('tables.copy', 'size-3.5') }}
            </button>
            <span x-show="copied" x-transition.opacity x-cloak role="status" class="inline-flex items-center gap-1 rounded-full bg-success-soft px-1.5 py-0.5 text-[11px] font-medium text-success">{{ __('gcego-tables::messages.copied') }}</span>
        @endif
    </div>
    @if ($cell['description'] ?? null)
        <p class="mt-0.5 line-clamp-1 max-w-md text-xs text-muted-foreground" title="{{ $cell['description'] }}">{{ $cell['description'] }}</p>
    @endif
@endif
