{{-- gcego-layouts::layouts.tabs — WAI-ARIA tabs; Alpine.js switches panels, every panel is server-rendered --}}
<div id="{{ $layout->id() }}"{{ $layout->class('root', 'flex min-w-0 flex-col') }} x-data="{{ $alpine }}"{{ $layout->key() }}>
    <div role="tablist" aria-orientation="horizontal"@if ($layout->title() !== null) aria-label="{{ $layout->title() }}"@endif{{ $layout->class('tablist', implode(' ', $presets::TabList)) }} x-on:keydown.arrow-right.prevent="move(1)" x-on:keydown.arrow-left.prevent="move(-1)" x-on:keydown.home.prevent="focus(0)" x-on:keydown.end.prevent="focus(-1)">
        @foreach ($layout->regions as $index => $tab)
            @php($name = $names[$index])
            @php($selected = $name === $active)
            @php($icon = $tab->icon('size-4'))
            <button type="button" role="tab" id="{{ $tab->id('tab') }}" aria-controls="{{ $tab->id('panel') }}" aria-selected="{{ $selected ? 'true' : 'false' }}" tabindex="{{ $selected ? '0' : '-1' }}" x-ref="tab-{{ $name }}" x-on:click="select({{ \Illuminate\Support\Js::from($name) }})" x-bind:aria-selected="active === {{ \Illuminate\Support\Js::from($name) }} ? 'true' : 'false'" x-bind:tabindex="active === {{ \Illuminate\Support\Js::from($name) }} ? 0 : -1"@if ($layout->live()) wire:ignore.self @endif{{ $layout->class('tab', implode(' ', $presets::Tab)) }}>
                @if ($icon !== null)
                    <span aria-hidden="true">{{ $icon }}</span>
                @endif
                <span>{{ $tab->title() }}</span>
            </button>
        @endforeach
    </div>
    @foreach ($layout->regions as $index => $tab)
        @php($name = $names[$index])
        <div role="tabpanel" id="{{ $tab->id('panel') }}" aria-labelledby="{{ $tab->id('tab') }}" tabindex="0"@if ($name !== $active) hidden @endif x-bind:hidden="active !== {{ \Illuminate\Support\Js::from($name) }}"@if ($layout->live()) wire:ignore.self @endif{{ $tab->class('root', $layout->classes('panel', implode(' ', $presets::TabPanel))) }}{{ $tab->key() }}>{{ $tab->content() }}</div>
    @endforeach
</div>
