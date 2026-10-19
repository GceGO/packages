@if ($field->get('helper_text') !== null)
    <p id="{{ $id }}-helper" {{ $styles->attribute('helper') }}>{{ __($field->get('helper_text')) }}</p>
@endif
@if ($field->get('hint') !== null)
    <p id="{{ $id }}-hint" {{ $styles->attribute('hint') }}>{{ __($field->get('hint')) }}</p>
@endif
@if ($errors !== [])
    <p id="{{ $id }}-error" {{ $styles->attribute('error') }}><x-gcego-icon name="heroicon:exclamation-circle" class="mt-0.5 size-4 shrink-0" decorative /><span>{{ implode(' ', $errors) }}</span></p>
@endif
