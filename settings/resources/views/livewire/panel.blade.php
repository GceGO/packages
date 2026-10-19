{{-- gcego-settings::livewire.panel — settings navigation, search, the open page and its history. --}}
@php($p = \Gcego\Settings\Presets\SettingsPresets::class)
<div class="{{ $styles->classes('wrapper', $p::Wrapper) }}" data-gcego-settings-panel>
    <aside class="{{ $styles->classes('sidebar', $p::Sidebar) }}">
        <form role="search" wire:submit.prevent aria-label="{{ __('gcego-settings::messages.search_label') }}">
            <label for="gcego-settings-search" class="mb-1 block text-sm font-medium text-gcego-text">{{ __('gcego-settings::messages.search_label') }}</label>
            <input
                id="gcego-settings-search"
                type="search"
                wire:model.live.debounce.300ms="search"
                maxlength="100"
                autocomplete="off"
                placeholder="{{ __('gcego-settings::messages.search_placeholder') }}"
                aria-controls="gcego-settings-results"
                class="{{ implode(' ', $p::Control) }}"
            >
        </form>

        <div id="gcego-settings-results" aria-live="polite">
            @if ($results !== null)
                <div class="{{ implode(' ', $p::Navigation) }}">
                    <p class="px-3 py-1 text-xs font-medium uppercase tracking-wide text-gcego-text-muted">{{ trans_choice('gcego-settings::messages.results', count($results), ['count' => count($results)]) }}</p>
                    @forelse ($results as $result)
                        <a href="{{ $this->linkTo($result['page'], $result['key']) }}" class="{{ implode(' ', $p::NavigationLink) }} flex-col items-start gap-0" wire:key="result-{{ $result['key'] }}">
                            <span class="font-medium">{{ $result['label'] }}</span>
                            <span class="text-xs text-gcego-text-muted">{{ $result['page_title'] }}@if ($result['section']) · {{ $result['section'] }}@endif</span>
                        </a>
                    @empty
                        <p class="px-3 py-2 text-sm text-gcego-text-muted">{{ __('gcego-settings::messages.no_results') }}</p>
                    @endforelse
                </div>
            @endif
        </div>

        <nav aria-label="{{ __('gcego-settings::messages.navigation') }}" class="{{ $styles->classes('navigation', $p::Navigation) }}">
            @forelse ($screens as $item)
                @php($current = $active !== null && $active->page->pageName() === $item->page->pageName())
                <a
                    href="{{ $this->linkTo($item->page->pageName()) }}"
                    @if ($current) aria-current="page" @endif
                    class="{{ implode(' ', $p::NavigationLink) }} {{ $current ? implode(' ', $p::NavigationActive) : '' }}"
                    wire:key="nav-{{ $item->page->pageName() }}"
                >
                    {{ \Gcego\Luminaries\Facades\Luminaries::svg($item->page->icon(), 'size-4 shrink-0 text-gcego-text-muted') }}
                    <span class="min-w-0 flex-1 truncate">{{ __($item->page->title()) }}</span>
                    <span class="text-xs text-gcego-text-muted">{{ $item->context->target->label() }}</span>
                </a>
            @empty
                <p class="px-3 py-2 text-sm text-gcego-text-muted">{{ __('gcego-settings::messages.no_pages') }}</p>
            @endforelse
        </nav>
    </aside>

    <div class="{{ implode(' ', $p::Content) }}">
        @if ($active === null)
            <div class="{{ $styles->classes('empty', $p::Empty) }}">
                <p>{{ __('gcego-settings::messages.no_pages') }}</p>
            </div>
        @else
            <header class="{{ implode(' ', $p::Header) }}">
                <h1 class="{{ implode(' ', $p::Title) }}">{{ __($active->page->title()) }}</h1>
                @if ($active->page->description())
                    <p class="{{ $styles->classes('description', $p::Description) }}">{{ __($active->page->description()) }}</p>
                @endif
            </header>

            <div role="tablist" aria-label="{{ __('gcego-settings::messages.views') }}" class="flex gap-2 border-b border-gcego-border">
                @foreach (['settings', 'history'] as $view)
                    <button
                        type="button"
                        role="tab"
                        id="gcego-settings-tab-{{ $view }}"
                        aria-controls="gcego-settings-view-{{ $view }}"
                        aria-selected="{{ $tab === $view ? 'true' : 'false' }}"
                        tabindex="{{ $tab === $view ? '0' : '-1' }}"
                        wire:click="$set('tab', '{{ $view }}')"
                        x-on:keydown.right.prevent="$el.nextElementSibling?.focus()"
                        x-on:keydown.left.prevent="$el.previousElementSibling?.focus()"
                        class="-mb-px border-b-2 px-3 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-gcego-ring {{ $tab === $view ? 'border-gcego-primary text-gcego-primary' : 'border-transparent text-gcego-text-muted hover:text-gcego-text' }}"
                    >{{ __('gcego-settings::messages.tabs.'.$view) }}</button>
                @endforeach
            </div>

            <div id="gcego-settings-view-{{ $tab }}" role="tabpanel" aria-labelledby="gcego-settings-tab-{{ $tab }}" tabindex="0">
                @if ($tab === 'settings')
                    <livewire:gcego-settings-form :page="$active->page->pageName()" :key="'settings-form-'.$active->page->pageName()" />
                @else
                    <section class="{{ $styles->classes('history', $p::History) }}" aria-label="{{ __('gcego-settings::messages.tabs.history') }}" wire:loading.class="opacity-60">
                        @if ($history === null || $history->isEmpty())
                            <div class="{{ $styles->classes('empty', $p::Empty) }}"><p>{{ __('gcego-settings::messages.history_empty') }}</p></div>
                        @else
                            <ol class="{{ implode(' ', $p::HistoryList) }}">
                                @foreach ($history->items() as $entry)
                                    @php($meta = $labels[$entry->key] ?? ['label' => $entry->key, 'sensitive' => true])
                                    <li class="{{ implode(' ', $p::HistoryItem) }}" wire:key="change-{{ $entry->ulid }}">
                                        <div class="min-w-0">
                                            <p class="font-medium text-gcego-text">{{ $meta['label'] }} <span class="font-normal text-gcego-text-muted">· {{ $entry->changeType()->label() }}</span></p>
                                            @if ($entry->redacted || $meta['sensitive'])
                                                <p class="{{ implode(' ', $p::Value) }}">{{ __('gcego-settings::messages.redacted') }}</p>
                                            @elseif ($entry->old_value !== null || $entry->new_value !== null)
                                                <p class="{{ implode(' ', $p::Value) }}">
                                                    <span class="sr-only">{{ __('gcego-settings::messages.from') }}</span>{{ \Illuminate\Support\Str::limit($entry->old_value ?? '—', 120) }}
                                                    <span aria-hidden="true">→</span><span class="sr-only">{{ __('gcego-settings::messages.to') }}</span>
                                                    {{ \Illuminate\Support\Str::limit($entry->new_value ?? '—', 120) }}
                                                </p>
                                            @endif
                                        </div>
                                        <p class="shrink-0 text-xs text-gcego-text-muted">
                                            {{ $entry->actor_name !== null ? __('gcego-settings::messages.system_actor', ['name' => $entry->actor_name]) : (data_get($entry->actor, 'name') ?? __('gcego-settings::messages.someone')) }}
                                            · <time datetime="{{ $entry->created_at?->toAtomString() }}">{{ $entry->created_at?->diffForHumans() }}</time>
                                        </p>
                                    </li>
                                @endforeach
                            </ol>
                            @if ($history->hasPages())
                                <nav aria-label="{{ __('gcego-settings::messages.pagination') }}" class="flex items-center justify-between gap-3 border-t border-gcego-border px-4 py-3 text-sm">
                                    <button type="button" wire:click="historyPrevious" @disabled($history->onFirstPage()) class="{{ implode(' ', $p::Link) }} disabled:opacity-50">{{ __('gcego-settings::messages.newer') }}</button>
                                    <span class="text-gcego-text-muted">{{ __('gcego-settings::messages.page', ['page' => $history->currentPage(), 'pages' => $history->lastPage()]) }}</span>
                                    <button type="button" wire:click="historyNext" @disabled(! $history->hasMorePages()) class="{{ implode(' ', $p::Link) }} disabled:opacity-50">{{ __('gcego-settings::messages.older') }}</button>
                                </nav>
                            @endif
                        @endif
                    </section>
                @endif
            </div>
        @endif
    </div>
</div>
