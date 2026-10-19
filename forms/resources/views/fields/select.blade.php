<div class="{{ $styles('root') }} {{ $span }}" data-gcego-field="{{ $path }}">
    @include('gcego-forms::fields.partials.label')
    <x-gcego-forms::listbox
        :id="$id"
        :binding="$binding"
        :options="$options"
        :value="$value"
        :live="$live"
        :model="$model"
        :multiple="$field->isMultiple()"
        :searchable="(bool) $field->get('searchable')"
        :placeholder="$field->get('placeholder') !== null ? __($field->get('placeholder')) : null"
        :disabled="$disabled"
        :required="$required"
        :invalid="$errors !== []"
        :describedby="$describedBy !== '' ? $describedBy : null"
    />
    @include('gcego-forms::fields.partials.messages')
</div>
