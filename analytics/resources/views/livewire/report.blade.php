{{-- gcego-analytics::livewire.report --}}
@php($p = \Gcego\Analytics\Presets\AnalyticsPresets::class)
<div class="{{ $styles->classes('wrapper', $p::Wrapper) }}">
    <section class="{{ $styles->classes('report', $p::Report) }}" aria-labelledby="gcego-report-{{ $this->getId() }}">
        <header class="{{ $styles->classes('header', $p::Header) }}">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 id="gcego-report-{{ $this->getId() }}" class="{{ implode(' ', $p::Title) }}">{{ $definition?->title() }}</h1>
                    @if ($definition?->description())
                        <p class="{{ implode(' ', $p::Description) }}">{{ $definition->description() }}</p>
                    @endif
                </div>
                @if ($formats !== [])
                    <div class="relative" x-data="{ open: false }">
                        <button type="button" class="{{ implode(' ', $p::Button) }}" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()" aria-expanded="false" aria-haspopup="true" aria-controls="gcego-export-{{ $this->getId() }}">{{ __('gcego-analytics::messages.export') }}</button>
                        <div id="gcego-export-{{ $this->getId() }}" class="{{ implode(' ', $p::Panel) }}" x-show="open" x-cloak x-on:keydown.escape.window="open = false" x-on:click.outside="open = false">
                            @foreach ($formats as $format)
                                <button type="button" wire:click="export('{{ $format }}')" x-on:click="open = false" class="{{ implode(' ', $p::Link) }} block py-1">{{ __('gcego-analytics::messages.export_as', ['format' => strtoupper($format)]) }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </header>

        <form class="{{ $styles->classes('filters', $p::Filters) }}" wire:submit.prevent role="search" aria-label="{{ __('gcego-analytics::messages.filters') }}">
            @if ($definition?->defaultRange() !== null || $range !== '')
                @include('gcego-analytics::partials.period', ['default' => $definition?->defaultRange()?->value ?? 'last_30_days', 'range' => $range])
            @endif
            @foreach ($definition?->filters() ?? [] as $filter)
                <label class="flex flex-col gap-1 {{ implode(' ', $p::FilterLabel) }}">
                    {{ $filter->label() }}
                    @if (($options[$filter->nodeName()] ?? []) !== [])
                        <select wire:model.live="filters.{{ $filter->nodeName() }}" @if ($filter->isMultiple()) multiple @endif class="{{ implode(' ', $p::Control) }}">
                            @unless ($filter->isMultiple())<option value="">{{ __('gcego-analytics::messages.all') }}</option>@endunless
                            @foreach ($options[$filter->nodeName()] as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" inputmode="decimal" wire:model.live.debounce.400ms="filters.{{ $filter->nodeName() }}" class="{{ implode(' ', $p::Control) }}">
                    @endif
                </label>
            @endforeach
            <div class="flex items-end">
                <button type="button" wire:click="resetFilters" class="{{ implode(' ', $p::Link) }}">{{ __('gcego-analytics::messages.reset') }}</button>
            </div>
        </form>

        <div role="status" aria-live="polite" class="px-4 pt-3 text-sm text-gcego-text-muted sm:px-6">
            <span wire:loading>{{ __('gcego-analytics::messages.loading') }}</span>
            @if ($message)<span wire:loading.remove>{{ $message }}</span>@endif
        </div>
        @error('analytics')
            <p class="mx-4 mt-3 {{ $styles->classes('error', $p::Notice) }}" role="alert">{{ $message }}</p>
        @enderror

        <div class="relative" wire:loading.class="opacity-60">
            @if ($result !== null)
                @include('gcego-analytics::partials.table', ['result' => $result, 'styles' => $styles, 'drill' => $drill, 'sortable' => true])
            @endif
        </div>

        @if ($result !== null)
            <footer class="{{ implode(' ', $p::Footer) }}">
                <p>
                    {{ __('gcego-analytics::messages.page', ['page' => $result->page, 'pages' => $result->lastPage(), 'total' => $result->total]) }}
                    · <time datetime="{{ $result->generatedAt->toIso8601String() }}">{{ __('gcego-analytics::messages.generated', ['time' => $result->generatedAt->diffForHumans()]) }}</time>@if ($result->cached) ({{ __('gcego-analytics::messages.from_cache') }})@endif
                </p>
                @if ($result->lastPage() > 1)
                    <nav aria-label="{{ __('gcego-analytics::messages.pagination') }}" class="{{ implode(' ', $p::Pagination) }}">
                        <button type="button" wire:click="goToPage({{ $result->page - 1 }})" @disabled($result->page <= 1) class="{{ implode(' ', $p::PageButton) }}">{{ __('gcego-analytics::messages.previous') }}</button>
                        <button type="button" wire:click="goToPage({{ $result->page + 1 }})" @disabled($result->page >= $result->lastPage()) class="{{ implode(' ', $p::PageButton) }}">{{ __('gcego-analytics::messages.next') }}</button>
                    </nav>
                @endif
            </footer>
        @endif
    </section>
</div>
