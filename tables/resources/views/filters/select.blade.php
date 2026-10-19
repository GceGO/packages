<div class="flex flex-col gap-1.5" data-gcego-filter="{{ $name }}">
    <span id="{{ $id }}-label" class="{{ implode(' ', $presets::FilterLabel) }}">{{ __($filter->label()) }}</span>
    <x-gcego-tables::listbox
        :id="$id"
        :binding="$binding"
        :options="$filter->options($model)"
        :value="$state"
        :multiple="$filter->isMultiple()"
        :placeholder="$filter->get('placeholder') ? __($filter->get('placeholder')) : null"
        :labelledby="$id.'-label'" />
</div>
