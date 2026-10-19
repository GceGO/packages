{{-- gcego-layouts::layouts.empty-state --}}
@php($icon = $layout->icon('size-5'))
<div id="{{ $layout->id() }}"{{ $layout->class('root', implode(' ', $presets::EmptyState)) }}{{ $layout->key() }}>
    @if ($icon !== null)
        <span aria-hidden="true"{{ $layout->class('icon', implode(' ', $presets::EmptyIcon)) }}>{{ $icon }}</span>
    @endif
    <p{{ $layout->class('title', implode(' ', $presets::EmptyTitle)) }}>{{ $layout->title() }}</p>
    @if ($layout->description() !== null)
        <p{{ $layout->class('description', implode(' ', $presets::EmptyDescription)) }}>{{ $layout->description() }}</p>
    @endif
    @if ($layout->items !== [])
        <div{{ $layout->class('actions', implode(' ', $presets::EmptyActions)) }}>{{ $layout->content() }}</div>
    @endif
</div>
