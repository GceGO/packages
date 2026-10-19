@php($pagination = $frame->pagination)
@if ($pagination !== null && ! $frame->error && (! $frame->isEmpty() || $frame->state->page > 1))
    <footer class="{{ $styles('footer') }}">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            @if ($pagination->total !== null && $pagination->from !== null)
                <p aria-live="polite"><span aria-hidden="true">{!! __('gcego-tables::messages.showing_rich', ['from' => '<span class="font-semibold tabular-nums text-foreground">'.e($pagination->from).'</span>', 'to' => '<span class="font-semibold tabular-nums text-foreground">'.e($pagination->to).'</span>', 'total' => '<span class="font-semibold tabular-nums text-foreground">'.e(number_format($pagination->total)).'</span>']) !!}</span><span class="sr-only">{{ __('gcego-tables::messages.showing', ['from' => $pagination->from, 'to' => $pagination->to, 'total' => $pagination->total]) }}</span></p>
            @else
                <p aria-live="polite">{{ trans_choice('gcego-tables::messages.on_page', $pagination->count, ['count' => $pagination->count]) }}</p>
            @endif
            @if (count($frame->perPageOptions) > 1)
                <div class="flex items-center gap-2 whitespace-nowrap" data-gcego-per-page="{{ $frame->state->perPage }}">
                    <span id="{{ $frame->id('per-page-label') }}">{{ __('gcego-tables::messages.per_page') }}</span>
                    <div class="w-20">
                        <x-gcego-tables::listbox :id="$frame->id('per-page')" binding="perPage" cast="number" :empty-value="0" :value="$frame->state->perPage" :fallback="$frame->state->perPage" :options="array_combine(array_map('strval', $frame->perPageOptions), array_map('strval', $frame->perPageOptions))" size="sm" upward :clearable="false" :labelledby="$frame->id('per-page-label')" />
                    </div>
                </div>
            @endif
        </div>
        <nav aria-label="{{ __('gcego-tables::messages.pagination') }}" class="{{ $styles('pagination') }}">
            @if ($pagination->mode === \Gcego\Data\Pagination\PaginationMode::Cursor)
                <div class="{{ implode(' ', $presets::PageGroup) }}">
                    <button type="button" wire:click="gotoCursor({{ \Illuminate\Support\Js::from($pagination->previousCursor) }})" @disabled($pagination->previousCursor === null) class="{{ implode(' ', $presets::PageButton) }}" aria-label="{{ __('gcego-tables::messages.previous') }}">{{ $icons->svg('tables.previous-page', 'size-4') }}</button>
                    <button type="button" wire:click="gotoCursor({{ \Illuminate\Support\Js::from($pagination->nextCursor) }})" @disabled($pagination->nextCursor === null) class="{{ implode(' ', $presets::PageButton) }}" aria-label="{{ __('gcego-tables::messages.next') }}">{{ $icons->svg('tables.next-page', 'size-4') }}</button>
                </div>
            @else
                @php($current = $pagination->currentPage ?? $frame->state->page)
                @if ($pagination->lastPage !== null && $pagination->lastPage > 7)
                    <form class="hidden items-center gap-2 lg:flex" x-data="{ page: '' }" x-on:submit.prevent="const target = parseInt(page, 10); if (target >= 1 && target <= {{ $pagination->lastPage }}) { $wire.gotoPage(target) } page = ''">
                        <label for="{{ $frame->id('goto') }}" class="whitespace-nowrap">{{ __('gcego-tables::messages.go_to_page') }}</label>
                        <input id="{{ $frame->id('goto') }}" x-model="page" type="number" min="1" max="{{ $pagination->lastPage }}" inputmode="numeric" placeholder="{{ $current }}" class="{{ implode(' ', $presets::FilterControl) }} h-8 w-16 px-2 text-center tabular-nums [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none">
                    </form>
                @endif
                <div class="{{ implode(' ', $presets::PageGroup) }}">
                    @if ($pagination->lastPage !== null && $pagination->lastPage > 7)
                        <button type="button" wire:click="gotoPage(1)" @disabled($current <= 1) class="{{ implode(' ', $presets::PageButton) }} hidden sm:inline-flex" aria-label="{{ __('gcego-tables::messages.first') }}">{{ $icons->svg('tables.first-page', 'size-4') }}</button>
                    @endif
                    <button type="button" wire:click="previousPage" @disabled($current <= 1) class="{{ implode(' ', $presets::PageButton) }}" aria-label="{{ __('gcego-tables::messages.previous') }}">{{ $icons->svg('tables.previous-page', 'size-4') }}</button>
                    @if ($pagination->lastPage !== null)
                        @php($pages = array_values(array_unique(array_filter([1, ...range(max(1, $current - 1), min($pagination->lastPage, $current + 1)), $pagination->lastPage], fn ($p) => $p >= 1 && $p <= $pagination->lastPage))))
                        @foreach ($pages as $index => $number)
                            @if ($index > 0 && $number - $pages[$index - 1] > 1)
                                <span class="inline-flex h-8 min-w-6 items-center justify-center text-muted-foreground" aria-hidden="true">…</span>
                            @endif
                            <button type="button" wire:click="gotoPage({{ $number }})" class="{{ implode(' ', $presets::PageButton) }} {{ $number !== $current && $number !== 1 && $number !== $pagination->lastPage ? 'hidden sm:inline-flex' : '' }}" @if ($number === $current) aria-current="page" @endif aria-label="{{ __('gcego-tables::messages.page', ['page' => $number]) }}">{{ $number }}</button>
                        @endforeach
                    @else
                        <span class="inline-flex h-8 items-center px-2 font-medium tabular-nums text-foreground" aria-current="page">{{ __('gcego-tables::messages.page', ['page' => $current]) }}</span>
                    @endif
                    <button type="button" wire:click="nextPage" @disabled(! $pagination->hasMorePages) class="{{ implode(' ', $presets::PageButton) }}" aria-label="{{ __('gcego-tables::messages.next') }}">{{ $icons->svg('tables.next-page', 'size-4') }}</button>
                    @if ($pagination->lastPage !== null && $pagination->lastPage > 7)
                        <button type="button" wire:click="gotoPage({{ $pagination->lastPage }})" @disabled($current >= $pagination->lastPage) class="{{ implode(' ', $presets::PageButton) }} hidden sm:inline-flex" aria-label="{{ __('gcego-tables::messages.last') }}">{{ $icons->svg('tables.last-page', 'size-4') }}</button>
                    @endif
                </div>
            @endif
        </nav>
    </footer>
@endif
