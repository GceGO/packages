{{-- Sidebar and mobile lists: links, headings, dividers and (collapsible) groups, recursively.
     $searchable: inside a sidebar with search, where links and groups show only while they match
     ($trail: the labels of the groups around them) and headings and dividers hide while searching. --}}
@php($searchable ??= false)
@php($trail ??= [])
@php($open = implode(' && ', array_filter(['! open', ($compactable ?? false) ? '! compact' : null, $searchable ? '! searching' : null])))
@foreach ($nodes as $node)
    @if ($node->isLink())
        <li @if ($searchable) x-show="found({{ \Illuminate\Support\Js::from($nav->keywords($node, $trail)) }})"@endif>@include('gcego-navigation::partials.link', ['node' => $node, 'base' => $presets::Item])</li>
    @elseif ($node->type === 'heading')
        <li{{ $nav->part($node, \Gcego\Navigation\Navigation::HeadingSlot, implode(' ', $presets::Heading)) }}@if ($searchable) x-show="! searching"@endif>{{ $node->label }}</li>
    @elseif ($node->type === 'divider')
        <li aria-hidden="true"{{ $nav->part($node, \Gcego\Navigation\Navigation::DividerSlot, implode(' ', $presets::Divider)) }}@if ($searchable) x-show="! searching"@endif></li>
    @else
        @php($icon = $nav->icon($node, 'size-4'))
        @php($items = implode(' ', $node->depth > 1 ? [...$presets::List, ...$presets::Nested] : $presets::List))
        @php($inner = [...$trail, (string) $node->label])
        <li{{ $nav->part($node, \Gcego\Navigation\Navigation::GroupSlot) }}@if ($node->collapsible) x-data="{ open: {{ $node->expanded ? 'true' : 'false' }} }"@endif @if ($searchable) x-show="foundAny({{ \Illuminate\Support\Js::from($nav->descendantKeywords($node->children, $inner)) }})"@endif>
            @if ($node->collapsible)
                <button type="button" id="{{ $nav->nodeId($node, 'toggle') }}" aria-expanded="{{ $node->expanded ? 'true' : 'false' }}" aria-controls="{{ $nav->nodeId($node, 'items') }}" x-bind:aria-expanded="open.toString()" x-on:click="open = ! open"@if ($compactable ?? false) x-bind:tabindex="compact ? -1 : null"@endif wire:ignore.self{{ $nav->part($node, \Gcego\Navigation\Navigation::GroupHeaderSlot, implode(' ', [...$presets::GroupHeader, ...$presets::GroupToggle])) }}>
                    @if ($icon !== null)
                        {{ $icon }}
                    @endif
                    <span class="{{ implode(' ', $presets::GroupLabel) }}">{{ $node->label }}</span>
                    <x-gcego-icon name="navigation.disclosure" size="none" :class="implode(' ', $presets::Chevron).($node->expanded ? ' '.implode(' ', $presets::ChevronOpen) : '')" x-bind:class="open && '{{ implode(' ', $presets::ChevronOpen) }}'" />
                </button>
                <ul id="{{ $nav->nodeId($node, 'items') }}" role="list" aria-labelledby="{{ $nav->nodeId($node, 'toggle') }}"@unless ($node->expanded) hidden @endunless x-bind:hidden="{{ $open }}" wire:ignore.self{{ $nav->part($node, \Gcego\Navigation\Navigation::GroupItemsSlot, $items) }}>
                    @include('gcego-navigation::partials.nodes', ['nodes' => $node->children, 'trail' => $inner])
                </ul>
            @else
                <span id="{{ $nav->nodeId($node, 'label') }}"{{ $nav->part($node, \Gcego\Navigation\Navigation::GroupHeaderSlot, implode(' ', $presets::GroupHeader)) }}>
                    @if ($icon !== null)
                        {{ $icon }}
                    @endif
                    <span class="{{ implode(' ', $presets::GroupLabel) }}">{{ $node->label }}</span>
                </span>
                <ul role="list" aria-labelledby="{{ $nav->nodeId($node, 'label') }}"{{ $nav->part($node, \Gcego\Navigation\Navigation::GroupItemsSlot, $items) }}>
                    @include('gcego-navigation::partials.nodes', ['nodes' => $node->children, 'trail' => $inner])
                </ul>
            @endif
        </li>
    @endif
@endforeach
