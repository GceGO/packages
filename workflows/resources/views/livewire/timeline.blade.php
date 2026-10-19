{{-- gcego-workflows::livewire.timeline — append-only history, newest first. --}}
@php($p = \Gcego\Workflows\Presets\WorkflowPresets::class)
<section class="{{ $styles->classes('timeline', $p::Card) }}" aria-labelledby="gcego-timeline-{{ $this->getId() }}">
    <header class="{{ implode(' ', $p::CardHeader) }}">
        <h2 id="gcego-timeline-{{ $this->getId() }}" class="{{ implode(' ', $p::CardTitle) }}">{{ __('gcego-workflows::messages.history') }}</h2>
    </header>
    @if ($record === null || $page === null || $page->isEmpty())
        <div class="{{ implode(' ', $p::Empty) }}">
            <p class="{{ implode(' ', $p::EmptyText) }}">{{ __('gcego-workflows::messages.history_empty') }}</p>
        </div>
    @else
        <ol class="{{ implode(' ', $p::List) }}">
            @foreach ($page->items() as $event)
                <li class="{{ implode(' ', $p::Item) }}" wire:key="event-{{ $event->ulid }}">
                    <span class="{{ implode(' ', $p::Dot) }}" aria-hidden="true"></span>
                    <div class="{{ implode(' ', $p::ItemText) }}">
                        <p class="{{ implode(' ', $p::ItemTitle) }}">{{ $labels->event($event, $record->workflow) }}</p>
                        @if (is_string($event->metadata['comment'] ?? null))
                            <p class="{{ implode(' ', $p::ItemBody) }}">{{ $event->metadata['comment'] }}</p>
                        @endif
                        <p class="{{ implode(' ', $p::ItemMeta) }}">
                            <span>{{ $labels->actor($event) }}</span>
                            <time datetime="{{ $event->created_at?->toIso8601String() }}">{{ $event->created_at?->diffForHumans() }}</time>
                        </p>
                    </div>
                </li>
            @endforeach
        </ol>
        @if ($page->hasPages())
            <nav aria-label="{{ __('gcego-workflows::messages.pagination') }}" class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                <button type="button" wire:click="previousPage('history')" @disabled($page->onFirstPage()) class="{{ implode(' ', $p::Link) }} disabled:opacity-50">{{ __('gcego-workflows::messages.newer') }}</button>
                <button type="button" wire:click="nextPage('history')" @disabled(! $page->hasMorePages()) class="{{ implode(' ', $p::Link) }} disabled:opacity-50">{{ __('gcego-workflows::messages.older') }}</button>
            </nav>
        @endif
    @endif
</section>
