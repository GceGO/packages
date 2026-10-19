<div class="{{ $styles('root') }} {{ $span }}" data-gcego-field="{{ $path }}">
    <label for="{{ $id }}" class="{{ implode(' ', $presets::ChoiceRow) }} cursor-pointer has-disabled:cursor-not-allowed">
        <input type="checkbox" role="switch" id="{{ $id }}" {{ $binding }} value="1" @if (! $live && $value) checked @endif @include('gcego-forms::fields.partials.aria') class="{{ implode(' ', $presets::Toggle) }}">
        <span aria-hidden="true" class="{{ implode(' ', $presets::ToggleTrack) }}"></span>
        <span {{ $styles->attribute('label') }}>{{ $label }}</span>
    </label>
    @include('gcego-forms::fields.partials.messages')
</div>
