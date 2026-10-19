{{-- gcego-notifications::inbox.inbox — the full inbox. --}}
@php($presets = \Gcego\Notifications\Presets\NotificationPresets::class)
@php($n = \Gcego\Notifications\Notification::class)
@php($items = array_values(array_filter($page->items())))
<div class="{{ $styles->classes($n::InboxSlot, $presets::Inbox) }}">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="{{ implode(' ', $presets::Title) }}">{{ __('gcego-notifications::messages.inbox') }}</h1>
        <button type="button" wire:click="markAllAsRead" class="{{ implode(' ', $presets::Link) }}">{{ __('gcego-notifications::messages.mark_all_read') }}</button>
    </div>

    <form class="{{ implode(' ', $presets::Toolbar) }}" wire:submit.prevent role="search" aria-label="{{ __('gcego-notifications::messages.filters') }}">
        <label class="{{ implode(' ', $presets::Field) }}">
            {{ __('gcego-notifications::messages.status') }}
            <select wire:model.live="status" class="{{ implode(' ', $presets::Control) }}">
                @foreach (\Gcego\Notifications\Database\NotificationQuery::Statuses as $status)
                    <option value="{{ $status }}">{{ __('gcego-notifications::messages.statuses.'.$status) }}</option>
                @endforeach
            </select>
        </label>
        <label class="{{ implode(' ', $presets::Field) }}">
            {{ __('gcego-notifications::messages.category') }}
            <select wire:model.live="category" class="{{ implode(' ', $presets::Control) }}">
                <option value="">{{ __('gcego-notifications::messages.all_categories') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="{{ implode(' ', $presets::Field) }}">
            {{ __('gcego-notifications::messages.from') }}
            <input type="date" wire:model.live="from" class="{{ implode(' ', $presets::Control) }}">
        </label>
        <label class="{{ implode(' ', $presets::Field) }}">
            {{ __('gcego-notifications::messages.until') }}
            <input type="date" wire:model.live="until" class="{{ implode(' ', $presets::Control) }}">
        </label>
    </form>

    @if ($selected !== [])
        <div class="{{ implode(' ', $presets::Toolbar) }}" role="group" aria-label="{{ __('gcego-notifications::messages.bulk') }}">
            <span class="text-sm text-gcego-text-muted" role="status">{{ trans_choice('gcego-notifications::messages.selected', count($selected), ['count' => count($selected)]) }}</span>
            <button type="button" wire:click="markAsRead" class="{{ implode(' ', $presets::Link) }}">{{ __('gcego-notifications::messages.mark_read') }}</button>
            <button type="button" wire:click="markAsUnread" class="{{ implode(' ', $presets::Link) }}">{{ __('gcego-notifications::messages.mark_unread') }}</button>
            @if ($deletable)
                <button type="button" wire:click="delete" wire:confirm="{{ __('gcego-notifications::messages.delete_confirm') }}" class="{{ implode(' ', $presets::Link) }}">{{ __('gcego-notifications::messages.delete') }}</button>
            @endif
        </div>
    @endif

    <div class="{{ implode(' ', $presets::InboxCard) }}" wire:loading.class="opacity-60" aria-busy="false" wire:loading.attr="aria-busy">
        @if ($items === [])
            <div class="{{ $styles->classes($n::EmptySlot, $presets::Empty) }}">
                {{ $styles->svg('notifications.notification', 'size-8 text-gcego-text-muted') }}
                <p class="{{ implode(' ', $presets::EmptyText) }}">{{ __('gcego-notifications::messages.empty') }}</p>
            </div>
        @else
            <ul role="list" class="divide-y divide-gcego-border">
                @foreach ($items as $item)
                    @include('gcego-notifications::partials.item', ['item' => $item, 'styles' => $styles, 'time' => $time, 'viewer' => $viewer, 'select' => true, 'inbox' => true])
                @endforeach
            </ul>
        @endif
    </div>

    @if ($page->hasPages())
        <nav aria-label="{{ __('gcego-notifications::messages.pagination') }}" class="flex items-center justify-between gap-3 text-sm">
            <button type="button" wire:click="previousPage" @disabled($page->onFirstPage()) class="{{ implode(' ', $presets::Link) }} disabled:opacity-50">{{ __('gcego-notifications::messages.previous') }}</button>
            <span class="text-gcego-text-muted">{{ __('gcego-notifications::messages.page', ['page' => $page->currentPage(), 'pages' => $page->lastPage()]) }}</span>
            <button type="button" wire:click="nextPage" @disabled(! $page->hasMorePages()) class="{{ implode(' ', $presets::Link) }} disabled:opacity-50">{{ __('gcego-notifications::messages.next') }}</button>
        </nav>
    @endif
</div>
