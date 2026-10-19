<div class="{{ $styles('root') }} {{ $span }}" data-gcego-field="{{ $path }}" x-data="{ nudge(up) { const input = this.$refs.input; up ? input.stepUp() : input.stepDown(); input.dispatchEvent(new Event('input', { bubbles: true })); input.dispatchEvent(new Event('change', { bubbles: true })); } }">
    @include('gcego-forms::fields.partials.label')
    <div class="{{ implode(' ', $presets::InputGroup) }}">
        @unless ($disabled || $field->get('readonly'))
            <button type="button" tabindex="-1" x-on:click="nudge(false)" class="{{ implode(' ', $presets::AddonButton) }} border-r border-border" aria-label="{{ __('gcego-forms::messages.decrease', ['label' => $label]) }}"><x-gcego-icon name="heroicon:minus" class="size-4" decorative /></button>
        @endunless
        <input
            id="{{ $id }}"
            x-ref="input"
            type="number"
            inputmode="{{ $field->isInteger() ? 'numeric' : 'decimal' }}"
            {{ $binding }}
            @unless ($live) value="{{ is_scalar($value) ? $value : '' }}" @endunless
            @if ($field->get('min') !== null) min="{{ $field->get('min') }}" @endif
            @if ($field->get('max') !== null) max="{{ $field->get('max') }}" @endif
            step="{{ $field->get('step') ?? ($field->isInteger() ? 1 : 'any') }}"
            @if ($field->get('placeholder') !== null) placeholder="{{ __($field->get('placeholder')) }}" @endif
            @if ($field->get('readonly')) readonly @endif
            @include('gcego-forms::fields.partials.aria')
            class="{{ $styles('input') }} {{ implode(' ', $presets::GroupedInput) }} flex-1 text-center tabular-nums [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
        >
        @unless ($disabled || $field->get('readonly'))
            <button type="button" tabindex="-1" x-on:click="nudge(true)" class="{{ implode(' ', $presets::AddonButton) }} border-l border-border" aria-label="{{ __('gcego-forms::messages.increase', ['label' => $label]) }}"><x-gcego-icon name="heroicon:plus" class="size-4" decorative /></button>
        @endunless
    </div>
    @include('gcego-forms::fields.partials.messages')
</div>
