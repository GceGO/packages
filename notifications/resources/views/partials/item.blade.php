{{-- gcego-notifications::partials.item — one notification (center and inbox). All text is escaped. --}}
@php($presets = \Gcego\Notifications\Presets\NotificationPresets::class)
@php($n = \Gcego\Notifications\Notification::class)
@php($payload = $item->payload)
@php($state = $item->read ? $styles->classes($n::ReadSlot, $presets::Read) : $styles->classes($n::UnreadSlot, $presets::Unread))
<li wire:key="notification-{{ $item->id }}" class="{{ $styles->classes($n::ItemSlot, $presets::Item, $state) }}">
    @if ($select ?? false)
        <input type="checkbox" wire:model.live="selected" value="{{ $item->id }}" aria-label="{{ __('gcego-notifications::messages.select', ['title' => $payload->displayTitle()]) }}" class="{{ implode(' ', [...$presets::Checkbox, 'mt-1']) }}">
    @endif
    <span aria-hidden="true" class="{{ $item->read ? 'mt-1.5 size-2 shrink-0' : implode(' ', $presets::UnreadDot) }}"></span>
    {{ $styles->svg($payload->icon, 'size-5 shrink-0 '.implode(' ', $presets::TypeIcon[$payload->type->value])) }}
    <div class="min-w-0 flex-1">
        <p class="{{ implode(' ', $presets::ItemTitle) }}">
            <span class="sr-only">{{ $item->read ? __('gcego-notifications::messages.read') : __('gcego-notifications::messages.unread') }}, {{ $payload->type->label() }}:</span>
            {{ $payload->displayTitle() }}
        </p>
        @if ($payload->displayBody() !== null && (! ($compact ?? false) || mb_strlen((string) $payload->displayBody()) <= 160))
            <p class="{{ implode(' ', $presets::ItemBody) }}">{{ $payload->displayBody() }}</p>
        @endif
        <div class="{{ implode(' ', $presets::ItemMeta) }}">
            {{ $time->html($item->createdAt, $viewer->user) }}
            @if ($payload->category !== \Gcego\Notifications\Definitions\NotificationCategories::Default && app(\Gcego\Notifications\Definitions\NotificationCategories::class)->has($payload->category))
                <span>· {{ app(\Gcego\Notifications\Definitions\NotificationCategories::class)->get($payload->category)->label() }}</span>
            @endif
        </div>
        <div class="{{ implode(' ', [...$presets::ToastActions]) }}">
            @foreach ($payload->actions as $index => $action)
                @if ($action['new_tab'])
                    <a href="{{ $action['url'] }}" target="_blank" rel="noopener noreferrer" wire:click="markAsRead('{{ $item->id }}')" class="{{ implode(' ', $presets::Link) }}">{{ $action['label'] }}</a>
                @else
                    <a href="{{ $action['url'] }}" wire:click.prevent="open('{{ $item->id }}', {{ $index }})" class="{{ implode(' ', $presets::Link) }}">{{ $action['label'] }}</a>
                @endif
            @endforeach
            @if (! $item->read)
                <button type="button" wire:click="markAsRead('{{ $item->id }}')" class="{{ implode(' ', $presets::Link) }}">{{ __('gcego-notifications::messages.mark_read') }}<span class="sr-only">: {{ $payload->displayTitle() }}</span></button>
            @elseif ($inbox ?? false)
                <button type="button" wire:click="markAsUnread('{{ $item->id }}')" class="{{ implode(' ', $presets::Link) }}">{{ __('gcego-notifications::messages.mark_unread') }}<span class="sr-only">: {{ $payload->displayTitle() }}</span></button>
            @endif
        </div>
    </div>
</li>
