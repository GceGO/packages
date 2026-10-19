{{-- A group or dropdown of the top navigation: a disclosure button and a panel of links (not an ARIA menu). --}}
@php($icon = $nav->icon($node, 'size-4'))
@php($align = $node->component instanceof \Gcego\Navigation\Components\Dropdown ? $node->component->align() : 'start')
<li{{ $nav->part($node, \Gcego\Navigation\Navigation::GroupSlot, 'relative') }} x-data="{{ $nav->dropdownState() }}" x-on:keydown.escape.prevent.stop="close(true)" x-on:click.outside="close(false)" x-on:focusout="$el.contains($event.relatedTarget) || $event.relatedTarget === null || close(false)">
    <button type="button" x-ref="trigger" id="{{ $nav->nodeId($node, 'toggle') }}" aria-expanded="false" aria-controls="{{ $nav->nodeId($node, 'menu') }}" x-bind:aria-expanded="open.toString()" x-on:click="toggle()" x-on:keydown.arrow-down.prevent="show(0)" x-on:keydown.arrow-up.prevent="show(-1)" wire:ignore.self{{ $nav->link($node, $presets::TopbarItem) }}>
        @if ($icon !== null)
            {{ $icon }}
        @endif
        <span>{{ $node->label }}</span>
        <x-gcego-icon name="navigation.disclosure" size="none" class="size-4 shrink-0 transition-transform duration-200 motion-reduce:transition-none" x-bind:class="open && '{{ implode(' ', $presets::ChevronOpen) }}'" />
    </button>
    <div id="{{ $nav->nodeId($node, 'menu') }}" x-ref="panel" hidden x-bind:hidden="! open" wire:ignore.self x-on:keydown.arrow-down.prevent="move(1)" x-on:keydown.arrow-up.prevent="move(-1)" x-on:keydown.home.prevent="focusItem(0)" x-on:keydown.end.prevent="focusItem(-1)"{{ $nav->part($node, \Gcego\Navigation\Navigation::DropdownSlot, implode(' ', $presets::Dropdown), $presets::DropdownAlign[$align]) }}>
        <ul role="list" class="{{ implode(' ', $presets::List) }}">
            @include('gcego-navigation::partials.menu', ['nodes' => $node->children])
        </ul>
    </div>
</li>
