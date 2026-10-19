<div class="{{ $styles('root') }} {{ $span }}" data-gcego-field="{{ $path }}">
    <label for="{{ $id }}" class="{{ implode(' ', $presets::ChoiceRow) }} cursor-pointer has-disabled:cursor-not-allowed">
        <x-gcego-forms::checkbox :binding="$binding" :id="$id" value="1" :checked="! $live && $value" :aria-describedby="$describedBy !== '' ? $describedBy : null" :aria-invalid="$errors !== [] ? 'true' : null" :required="$required" :aria-required="$required ? 'true' : null" :disabled="$disabled" />
        <span {{ $styles->attribute('label') }}>{{ $label }}@if ($required)<span aria-hidden="true" class="{{ implode(' ', $presets::Required) }}"> *</span>@endif</span>
    </label>
    @include('gcego-forms::fields.partials.messages')
</div>
