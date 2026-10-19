@php($counted = $field->get('counter') && $field->get('max_length'))
<div class="{{ $styles('root') }} {{ $span }}" data-gcego-field="{{ $path }}" @if ($counted) x-data="{ count: {{ is_string($value) ? mb_strlen($value) : 0 }}, max: {{ (int) $field->get('max_length') }} }" @endif>
    @include('gcego-forms::fields.partials.label')
    <textarea
        id="{{ $id }}"
        {{ $binding }}
        rows="{{ $field->get('rows') }}"
        @if ($field->get('placeholder') !== null) placeholder="{{ __($field->get('placeholder')) }}" @endif
        @if ($field->get('max_length') !== null) maxlength="{{ $field->get('max_length') }}" @endif
        @if ($field->get('readonly')) readonly @endif
        @if ($counted) x-on:input="count = $el.value.length" @endif
        @include('gcego-forms::fields.partials.aria')
        class="{{ $styles('input') }} min-h-24 resize-y leading-relaxed"
    >@unless ($live){{ is_string($value) ? $value : '' }}@endunless</textarea>
    @if ($counted)
        <p class="-mt-0.5 self-end text-xs tabular-nums text-muted-foreground" x-bind:class="count >= max && 'text-warning font-medium'" aria-live="polite"><span x-text="count">{{ is_string($value) ? mb_strlen($value) : 0 }}</span> / {{ $field->get('max_length') }}</p>
    @endif
    @include('gcego-forms::fields.partials.messages')
</div>
