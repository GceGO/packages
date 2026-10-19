{{-- gcego-navigation::navigation.mobile — a trigger and a modal drawer. Panel shells can open it with
     window.dispatchEvent(new CustomEvent('gcego-navigation-open', { detail: { id: 'navigation:admin' } })). --}}
<div id="{{ $nav->id() }}" x-data="{{ $nav->mobileState() }}" x-on:gcego-navigation-open.window="matches($event) && show()" x-on:gcego-navigation-close.window="matches($event) && close()" x-on:gcego-navigation-toggle.window="matches($event) && toggle()" x-on:keydown.escape.window="close()">
    <button type="button" x-ref="trigger" aria-expanded="false" aria-controls="{{ $nav->id('drawer') }}" x-bind:aria-expanded="open.toString()" x-on:click="show()" class="{{ implode(' ', $presets::MobileTrigger) }}">
        {{ $nav->svg('navigation.menu', 'size-6') }}
        <span class="sr-only">{{ __('gcego-navigation::messages.open') }}</span>
    </button>
    <div id="{{ $nav->id('drawer') }}" role="dialog" aria-modal="true" aria-label="{{ $nav->label() }}" hidden x-bind:hidden="! open" wire:ignore.self class="{{ implode(' ', $presets::MobileDialog) }}">
        <div aria-hidden="true" x-on:click="close()" class="{{ implode(' ', $presets::MobileBackdrop) }}"></div>
        <div x-ref="panel" x-on:keydown.tab="trap($event)" x-on:click="$event.target.closest('a[href]') && close()"{{ $nav->class(\Gcego\Navigation\Navigation::MobileSlot, implode(' ', $presets::MobilePanel)) }}>
            <button type="button" x-on:click="close()" class="{{ implode(' ', $presets::MobileClose) }}">
                {{ $nav->svg('navigation.close', 'size-6') }}
                <span class="sr-only">{{ __('gcego-navigation::messages.close') }}</span>
            </button>
            <nav aria-label="{{ $nav->label() }}">
                <ul role="list"{{ $nav->class(\Gcego\Navigation\Navigation::ListSlot, implode(' ', $presets::List)) }}>
                    @include('gcego-navigation::partials.nodes', ['nodes' => $nav->navigation->nodes, 'compactable' => false])
                </ul>
            </nav>
        </div>
    </div>
</div>
