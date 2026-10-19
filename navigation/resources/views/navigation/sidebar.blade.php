{{-- gcego-navigation::navigation.sidebar --}}
@php($compactable = $config->compactToggle)
@php($searchable = $config->search)
<nav id="{{ $nav->id() }}" aria-label="{{ $nav->label() }}"{{ $nav->class('root', implode(' ', $presets::Sidebar)) }}@if ($compactable || $searchable) x-data="{{ $nav->sidebarState() }}"@endif @if ($compactable) x-bind:data-compact="compact" x-on:keydown.escape="tip = null"@endif @if ($searchable) x-on:keydown.slash.window="shortcut($event)"@endif>
    @if ($compactable || $searchable)
        <div class="{{ implode(' ', $presets::Toolbar) }}">
            @if ($searchable)
                <div role="search" class="{{ implode(' ', $presets::Search) }}">
                    {{ $nav->svg('navigation.search', implode(' ', $presets::SearchIcon)) }}
                    <input type="text" role="searchbox" x-ref="search" x-model="query" x-on:keydown.escape.prevent.stop="clear()" x-on:keydown.enter.prevent="go()" aria-label="{{ __('gcego-navigation::messages.search') }}" aria-controls="{{ $nav->id('list') }}" placeholder="{{ __('gcego-navigation::messages.search_placeholder') }}" autocomplete="off" spellcheck="false" class="{{ implode(' ', $presets::SearchInput) }}">
                    <kbd aria-hidden="true" x-show="! searching" class="{{ implode(' ', $presets::SearchHint) }}">/</kbd>
                    <button type="button" x-cloak x-show="searching" x-on:click="query = ''; $refs.search.focus()" class="{{ implode(' ', $presets::SearchClear) }}">
                        {{ $nav->svg('navigation.close', 'size-4') }}
                        <span class="sr-only">{{ __('gcego-navigation::messages.search_clear') }}</span>
                    </button>
                </div>
                @if ($compactable)
                    <button type="button" x-on:click="search()" aria-controls="{{ $nav->id('list') }}" class="{{ implode(' ', $presets::SearchButton) }}">
                        {{ $nav->svg('navigation.search', 'size-5') }}
                        <span class="sr-only">{{ __('gcego-navigation::messages.search') }}</span>
                    </button>
                @endif
            @endif
            @if ($compactable)
                <button type="button" aria-pressed="{{ $config->defaultCompact ? 'true' : 'false' }}" aria-controls="{{ $nav->id('list') }}" x-bind:aria-pressed="compact.toString()" x-on:click="toggle()" class="{{ implode(' ', $presets::CompactToggle) }}">
                    <span class="{{ implode(' ', $presets::CompactToggleIcon) }}">{{ $nav->svg('navigation.collapse', 'size-4') }}</span>
                    <span class="sr-only">{{ __('gcego-navigation::messages.compact') }}</span>
                </button>
            @endif
        </div>
    @endif
    <ul id="{{ $nav->id('list') }}" role="list"{{ $nav->class(\Gcego\Navigation\Navigation::ListSlot, implode(' ', $presets::List)) }}>
        @include('gcego-navigation::partials.nodes', ['nodes' => $nav->navigation->nodes, 'compactable' => $compactable, 'searchable' => $searchable, 'trail' => []])
    </ul>
    @if ($searchable)
        <p role="status" x-cloak x-show="searching && ! foundAny({{ \Illuminate\Support\Js::from($nav->descendantKeywords($nav->navigation->nodes)) }})" class="{{ implode(' ', $presets::SearchEmpty) }}">{{ __('gcego-navigation::messages.search_empty') }}</p>
    @endif
</nav>
@if ($compactable)
    {{ $nav->compactPrelude() }}
@endif
