{{-- One link (an Item or ExternalLink). $base: the base classes; $compactable: inside a compactable sidebar,
     whose icon rail names the link in a tooltip. --}}
@php($component = $node->component)
@php($external = $node->type === 'external')
@php($newTab = $external && $component instanceof \Gcego\Navigation\Components\ExternalLink && $component->opensInNewTab())
@php($tooltip = $component instanceof \Gcego\Navigation\Components\Item ? $component->tooltip() : null)
@php($description = $component instanceof \Gcego\Navigation\Components\Item ? $component->description() : null)
@php($icon = $nav->icon($node))
@php($tip = ($compactable ?? false) ? \Illuminate\Support\Js::from($nav->nodeId($node, 'tip')) : null)
<a href="{{ $node->url }}"{{ $nav->link($node, $base) }}@if ($node->active) aria-current="page"@endif @if ($tooltip !== null) title="{{ $tooltip }}"@endif @if ($tip !== null) x-on:mouseenter="hoverTip({{ $tip }})" x-on:mouseleave="leaveTip()" x-on:focus="focusTip({{ $tip }})" x-on:blur="leaveTip()"@if ($tooltip !== null) x-bind:title="compact ? null : {{ \Illuminate\Support\Js::from($tooltip) }}"@endif @endif @if ($newTab) target="_blank" rel="noopener noreferrer"@elseif ($external) rel="noopener"@endif{{ $nav->navigate($node) }}@if ($searchable ?? false) data-gcego-search @endif>
    @if ($icon !== null)
        {{ $icon }}
    @endif
    <span{{ $nav->part($node, \Gcego\Chroma\Tailwind::Label, implode(' ', $presets::Label)) }}>
        {{ $node->label }}
        @if ($description !== null)
            <span class="{{ implode(' ', $presets::Description) }}">{{ $description }}</span>
        @endif
    </span>
    @if ($external)
        {{ $nav->svg('navigation.external', 'size-4 shrink-0 text-gcego-text-muted') }}
        @if ($newTab)
            <span class="sr-only">{{ __('gcego-navigation::messages.new_tab') }}</span>
        @endif
    @endif
    @if ($node->badge !== null)
        <span{{ $nav->part($node, \Gcego\Navigation\Navigation::BadgeSlot, implode(' ', $icon === null ? $presets::Badge : $presets::IconBadge), $node->badge->classes($icon !== null)) }}>
            @if ($node->badge->label !== null)
                <span aria-hidden="true">{{ $node->badge->text }}</span><span class="sr-only">{{ $node->badge->label }}</span>
            @else
                {{ $node->badge->text }}
            @endif
        </span>
    @endif
    @if ($tip !== null)
        <span aria-hidden="true" x-cloak x-show="compact && tip === {{ $tip }}" x-transition:enter="{{ implode(' ', $presets::TooltipEnter) }}" x-transition:enter-start="{{ implode(' ', $presets::TooltipHidden) }}" x-transition:enter-end="{{ implode(' ', $presets::TooltipShown) }}" x-transition:leave="{{ implode(' ', $presets::TooltipLeave) }}" x-transition:leave-start="{{ implode(' ', $presets::TooltipShown) }}" x-transition:leave-end="{{ implode(' ', $presets::TooltipHidden) }}" class="{{ implode(' ', $presets::Tooltip) }}">
            {{ $node->label }}
            @if ($node->badge !== null)
                <span class="{{ implode(' ', $presets::TooltipBadge) }}">{{ $node->badge->text }}</span>
            @endif
        </span>
    @endif
</a>
