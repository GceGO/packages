<div class="{{ $styles('root') }} {{ $span }}" data-gcego-field="{{ $path }}" x-data="{ reveal: false }">
    @include('gcego-forms::fields.partials.label')
    <div class="{{ implode(' ', $presets::InputGroup) }}">
        <x-gcego-icon name="heroicon:lock-closed" class="ml-3 size-4 shrink-0 self-center text-muted-foreground" decorative />
        {{-- Never prefilled: no value, hash or placeholder of the stored password is ever rendered. --}}
        <input
            id="{{ $id }}"
            x-bind:type="reveal ? 'text' : 'password'"
            type="password"
            {{ $binding }}
            autocomplete="{{ $field->get('autocomplete') ?? 'new-password' }}"
            @if ($field->get('max_length') !== null) maxlength="{{ $field->get('max_length') }}" @endif
            @include('gcego-forms::fields.partials.aria')
            class="{{ $styles('input') }} {{ implode(' ', $presets::GroupedInput) }} flex-1"
        >
        @if ($field->get('revealable'))
            <button type="button" x-on:click="reveal = ! reveal" x-bind:aria-pressed="reveal.toString()" aria-pressed="false" aria-controls="{{ $id }}" class="{{ implode(' ', $presets::AddonButton) }}" x-bind:aria-label="reveal ? @js(__('gcego-forms::messages.hide_password')) : @js(__('gcego-forms::messages.show_password'))" aria-label="{{ __('gcego-forms::messages.show_password') }}">
                <span x-show="! reveal" class="inline-flex"><x-gcego-icon name="heroicon:eye" class="size-4" decorative /></span>
                <span x-show="reveal" x-cloak class="inline-flex"><x-gcego-icon name="heroicon:eye-slash" class="size-4" decorative /></span>
            </button>
        @endif
    </div>
    @if ($field->isConfirmed())
        <label for="{{ $id }}-confirmation" {{ $styles->attribute('label') }}>{{ __('gcego-forms::messages.confirm', ['label' => $label]) }}</label>
        <div class="{{ implode(' ', $presets::InputGroup) }}">
            <x-gcego-icon name="heroicon:lock-closed" class="ml-3 size-4 shrink-0 self-center text-muted-foreground" decorative />
            <input id="{{ $id }}-confirmation" x-bind:type="reveal ? 'text' : 'password'" type="password" @if ($live) wire:model="{{ $model }}_confirmation" @else name="{{ $path }}_confirmation" @endif autocomplete="new-password" @disabled($disabled) class="{{ $styles('input') }} {{ implode(' ', $presets::GroupedInput) }} flex-1">
        </div>
    @endif
    @include('gcego-forms::fields.partials.messages')
</div>
