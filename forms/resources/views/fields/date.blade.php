<div class="{{ $styles('root') }} {{ $span }}" data-gcego-field="{{ $path }}">
    @include('gcego-forms::fields.partials.label')
    <x-gcego-forms::date-picker
        :id="$id"
        :binding="$binding"
        :value="$value"
        :live="$live"
        :model="$model"
        :mode="match ($field->inputType()) { 'time' => 'time', 'datetime-local' => 'datetime', default => 'date' }"
        :min="$field->get('min')"
        :max="$field->get('max')"
        :disabled="$disabled || (bool) $field->get('readonly')"
        :required="$required"
        :invalid="$errors !== []"
        :describedby="$describedBy !== '' ? $describedBy : null"
        :label="$label"
    />
    @include('gcego-forms::fields.partials.messages')
</div>
