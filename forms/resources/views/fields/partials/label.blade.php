<label for="{{ $id }}" id="{{ $id }}-label" {{ $styles->attribute('label') }}>
    {{ $label }}@if ($required)<span aria-hidden="true" class="{{ implode(' ', $presets::Required) }}"> *</span><span class="sr-only"> ({{ __('gcego-forms::messages.required') }})</span>@endif
</label>
