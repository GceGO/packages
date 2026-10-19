{{--
    <x-gcego-swatches::appearance-switcher />   Light / Dark / System buttons.
    Works without Alpine: the appearance controller handles [data-gcego-appearance-set]
    clicks and keeps aria-pressed in sync. Requires <x-gcego-swatches::appearance /> in <head>.
--}}
@props(['label' => __('Color mode')])
@php($id = 'gcego-appearance-'.substr(md5((string) $label), 0, 6))
<div role="group" aria-labelledby="{{ $id }}" {{ $attributes->class(['inline-flex items-center gap-1 rounded-md bg-surface-muted p-1']) }}>
    <span id="{{ $id }}" class="sr-only">{{ $label }}</span>
    @foreach (['light' => __('Light'), 'dark' => __('Dark'), 'system' => __('System')] as $mode => $text)
        <button type="button" data-gcego-appearance-set="{{ $mode }}" aria-pressed="false" class="inline-flex min-h-8 items-center rounded-sm px-2.5 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring aria-pressed:bg-surface aria-pressed:text-foreground aria-pressed:shadow-sm motion-reduce:transition-none">{{ $text }}</button>
    @endforeach
</div>
