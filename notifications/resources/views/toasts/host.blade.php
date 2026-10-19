{{-- gcego-notifications::toasts.host — toast regions. Text is set with x-text (never HTML); icons are cloned from
     server-rendered <template> elements by name; links were checked on the server. Routine toasts are announced politely; urgent errors (high or critical priority) assertively. Hovering or
     focusing a toast pauses the timers (WCAG 2.2.1). --}}
@php($presets = \Gcego\Notifications\Presets\NotificationPresets::class)
@php($n = \Gcego\Notifications\Notification::class)
<div>
@if (! $duplicate && $config->toasts)
    @include('gcego-notifications::toasts.script', ['presets' => $presets])
    <div wire:ignore x-data="gcegoToasts(@js($state))"
        x-on:{{ $state['event'] }}.window="($event.detail.toasts || []).forEach((toast) => add(toast))"
        x-on:gcego-action-completed.window="fromAction($event.detail.result)"
        x-on:gcego-notification-received.window="fromBroadcast($event.detail)"
        x-on:mouseenter="pause()" x-on:mouseleave="resume()" x-on:focusin="pause()" x-on:focusout="resume()"
        class="{{ $styles->classes($n::ToastWrapperSlot, $presets::ToastWrapper[$config->position]) }}">
        @foreach ($icons as $name => $svg)
            <template data-gcego-toast-icon="{{ $name }}">{!! $svg !!}</template>
        @endforeach
        @foreach (['polite' => 'status', 'assertive' => 'alert'] as $live => $role)
            <div role="{{ $role }}" aria-live="{{ $live }}" aria-relevant="additions" class="contents">
                <template x-for="toast in toasts.filter((toast) => toast.assertive === {{ $live === 'assertive' ? 'true' : 'false' }})" x-bind:key="toast.id">
                    <div x-transition.opacity class="{{ $styles->classes($n::ToastSlot, $presets::Toast) }}" x-bind:data-type="toast.type">
                        <span class="{{ implode(' ', $presets::ToastIconBox) }}" x-bind:class="iconClass(toast.type)" x-effect="renderIcon($el, toast)"></span>
                        <div class="{{ implode(' ', $presets::ToastText) }}">
                            <p class="{{ $styles->classes($n::ToastTitleSlot, $presets::ToastTitle) }}"><span class="sr-only" x-text="labels[toast.type] + ': '"></span><span x-text="toast.title"></span></p>
                            <p x-show="toast.body" class="{{ $styles->classes($n::ToastBodySlot, $presets::ToastBody) }}" x-text="toast.body"></p>
                            <div x-show="toast.actions.length" class="{{ $styles->classes($n::ToastActionsSlot, $presets::ToastActions) }}">
                                <template x-for="action in toast.actions" x-bind:key="action.url">
                                    <a x-bind:href="action.url" x-bind:target="action.new_tab ? '_blank' : null" x-bind:rel="action.new_tab ? 'noopener noreferrer' : null" class="{{ implode(' ', $presets::Link) }}" x-text="action.label"></a>
                                </template>
                            </div>
                        </div>
                        <button type="button" x-on:click="dismiss(toast.id)" class="{{ $styles->classes($n::ToastDismissSlot, $presets::Dismiss) }}">
                            {{ $styles->svg('notifications.dismiss', 'size-5') }}
                            <span class="sr-only">{{ __('gcego-notifications::messages.dismiss') }}</span>
                        </button>
                    </div>
                </template>
            </div>
        @endforeach
    </div>
@endif
</div>
