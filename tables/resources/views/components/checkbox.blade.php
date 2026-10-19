{{--
    <x-gcego-tables::checkbox wire:model.live="selected" value="12" aria-label="Select row 1" />
    A native checkbox (keyboard, forms and screen readers unchanged) drawn as a themed box with a check or,
    with :indeterminate="true", a dash for "partly selected".
--}}
@props(['indeterminate' => false])
<span class="relative inline-flex size-[18px] shrink-0 items-center justify-center align-middle">
    <input type="checkbox"
        @if ($indeterminate) x-init="$el.indeterminate = true" @endif
        {{ $attributes->class(['peer absolute inset-0 m-0 size-full cursor-pointer appearance-none rounded-[5px] border border-input bg-surface shadow-xs transition duration-150 hover:border-foreground/50 checked:border-primary checked:bg-primary checked:hover:bg-primary-hover indeterminate:border-primary indeterminate:bg-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:cursor-not-allowed disabled:opacity-40']) }}>
    <x-gcego-icon name="heroicon:check" class="pointer-events-none relative size-3 scale-50 text-primary-foreground opacity-0 transition duration-150 peer-checked:scale-100 peer-checked:opacity-100 peer-indeterminate:opacity-0" :stroke-width="3.5" decorative />
    <span class="pointer-events-none absolute h-0.5 w-2 scale-50 rounded-full bg-primary-foreground opacity-0 transition duration-150 peer-indeterminate:scale-100 peer-indeterminate:opacity-100" aria-hidden="true"></span>
</span>
