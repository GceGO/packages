@php($state = $context->state())
<div data-state-key="{{ $context->statePath()->key }}">
    <label for="{{ $context->domId('query') }}">Search</label>
    <input id="{{ $context->domId('query') }}" type="search" @if ($context->isLivewire()) wire:model.live="{{ $context->wireModel('query') }}" @endif value="{{ $state['query'] ?? '' }}">
    <output>Query: [{{ $state['query'] ?? '' }}], page {{ $state['page'] ?? 1 }}</output>
</div>
