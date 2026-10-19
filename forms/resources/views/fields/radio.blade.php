<fieldset class="{{ $styles('root') }} {{ $span }}" data-gcego-field="{{ $path }}" @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif @if ($errors !== []) aria-invalid="true" @endif @disabled($disabled)>
    <legend {{ $styles->attribute('label') }}>{{ $label }}@if ($required)<span aria-hidden="true" class="{{ implode(' ', $presets::Required) }}"> *</span>@endif</legend>
    <div class="mt-2 {{ implode(' ', $presets::ChoiceGrid) }}">
        @foreach ($options as $option)
            <label class="{{ implode(' ', $presets::ChoiceCard) }}">
                <x-gcego-forms::radio :binding="$binding" :id="$id.'-'.$loop->index" :value="$option->value" :disabled="$option->disabled" :required="$required" :checked="! $live && (string) $value === (string) $option->value" />
                <span class="min-w-0 leading-5">{{ __($option->label) }}</span>
            </label>
        @endforeach
    </div>
    @include('gcego-forms::fields.partials.messages')
</fieldset>
