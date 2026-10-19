@php($grouped = $field->get('prefix') !== null || $field->get('suffix') !== null)
<div class="{{ $styles('root') }} {{ $span }}" data-gcego-field="{{ $path }}">
    @include('gcego-forms::fields.partials.label')
    @if ($redacted)
        <input id="{{ $id }}" type="text" value="{{ $mask ?? '********' }}" disabled class="{{ $styles('input') }}">
    @else
        <div @class([implode(' ', $presets::InputGroup) => $grouped])>
            @if ($field->get('prefix') !== null)<span class="{{ implode(' ', $presets::Addon) }} border-r border-border">{{ $field->get('prefix') }}</span>@endif
            <input
                id="{{ $id }}"
                type="{{ $field->inputType() }}"
                {{ $binding }}
                @unless ($live) value="{{ is_scalar($value) ? $value : '' }}" @endunless
                @if ($field->get('placeholder') !== null) placeholder="{{ __($field->get('placeholder')) }}" @endif
                @if ($field->get('max_length') !== null) maxlength="{{ $field->get('max_length') }}" @endif
                @if ($field->get('min_length') !== null) minlength="{{ $field->get('min_length') }}" @endif
                @if ($field->get('pattern') !== null) pattern="{{ $field->get('pattern') }}" @endif
                @if ($field->get('autocomplete') !== null) autocomplete="{{ $field->get('autocomplete') }}" @endif
                @if ($field->get('readonly')) readonly @endif
                @if ($field->get('autofocus')) autofocus @endif
                @include('gcego-forms::fields.partials.aria')
                class="{{ $styles('input') }} @if ($grouped) {{ implode(' ', $presets::GroupedInput) }} flex-1 @endif"
            >
            @if ($field->get('suffix') !== null)<span class="{{ implode(' ', $presets::Addon) }} border-l border-border">{{ $field->get('suffix') }}</span>@endif
        </div>
    @endif
    @include('gcego-forms::fields.partials.messages')
</div>
