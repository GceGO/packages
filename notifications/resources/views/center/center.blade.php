{{-- gcego-notifications::center.center — the bell and the notification center (a disclosure: Escape and outside clicks
     close it and return focus to the bell). The unread count is in the button's accessible name. --}}
@php($presets = \Gcego\Notifications\Presets\NotificationPresets::class)
@php($n = \Gcego\Notifications\Notification::class)
<div class="relative" @if ($viewer !== null) x-data="{ open: false, toggle() { this.open = ! this.open; if (this.open) { $wire.load() } }, close(focus) { this.open = false; if (focus) { $refs.bell.focus() } } }" x-on:keydown.escape="close(true)" x-on:click.outside="close(false)" x-on:gcego-notification-received.window="$wire.$refresh()" x-on:gcego-notifications-refresh.window="$wire.$refresh()" @endif
    @if ($poll > 0 && $viewer !== null) wire:poll.{{ $poll }}s.visible="refresh" @endif>
@if ($viewer !== null)
    @php($label = $count === 0 ? __('gcego-notifications::messages.bell') : trans_choice('gcego-notifications::messages.bell_unread', $count, ['count' => $count]))
    <button type="button" x-ref="bell" x-on:click="toggle()" aria-expanded="false" x-bind:aria-expanded="open.toString()" aria-controls="gcego-notification-center-{{ $this->getId() }}" aria-label="{{ $label }}" class="{{ $styles->classes($n::BellSlot, $presets::Bell) }}">
        {{ $styles->svg('notifications.notification', 'size-6') }}
        @if ($count > 0)
            <span aria-hidden="true" class="{{ $styles->classes($n::BadgeSlot, $presets::Badge) }}">{{ $count > 99 ? '99+' : $count }}</span>
        @endif
    </button>
    <section id="gcego-notification-center-{{ $this->getId() }}" aria-label="{{ __('gcego-notifications::messages.center') }}" x-show="open" x-cloak style="display: none" class="{{ $styles->classes($n::CenterSlot, $presets::Center) }}">
        <header class="{{ implode(' ', $presets::CenterHeader) }}">
            <h2 class="{{ implode(' ', $presets::CenterTitle) }}">{{ __('gcego-notifications::messages.center') }}</h2>
            @if ($count > 0)
                <button type="button" wire:click="markAllAsRead" class="{{ implode(' ', $presets::Link) }}">{{ __('gcego-notifications::messages.mark_all_read') }}</button>
            @endif
        </header>
        @if ($items === null)
            <p wire:loading.remove class="{{ $styles->classes($n::LoadingSlot, $presets::Loading) }}" role="status">{{ __('gcego-notifications::messages.loading') }}</p>
        @elseif ($items === [])
            <div class="{{ $styles->classes($n::EmptySlot, $presets::Empty) }}">
                {{ $styles->svg('notifications.notification', 'size-8 text-gcego-text-muted') }}
                <p class="{{ implode(' ', $presets::EmptyText) }}">{{ __('gcego-notifications::messages.empty') }}</p>
            </div>
        @else
            <ul role="list" class="{{ $styles->classes($n::ListSlot, $presets::List) }}">
                @foreach ($items as $item)
                    @include('gcego-notifications::partials.item', ['item' => $item, 'styles' => $styles, 'time' => $time, 'viewer' => $viewer, 'compact' => true])
                @endforeach
            </ul>
        @endif
        @if ($inboxUrl !== null)
            <footer class="{{ implode(' ', $presets::CenterFooter) }}">
                <a href="{{ $inboxUrl }}" class="{{ implode(' ', $presets::Link) }}">{{ __('gcego-notifications::messages.view_all') }}</a>
            </footer>
        @endif
    </section>
@endif
</div>
