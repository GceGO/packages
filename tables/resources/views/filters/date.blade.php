<div class="flex flex-col gap-1.5" data-gcego-filter="{{ $name }}">
    <span id="{{ $id }}-label" class="{{ implode(' ', $presets::FilterLabel) }}">{{ __($filter->label()) }}</span>
    <x-gcego-tables::date-picker :id="$id" :binding="$binding" :value="$state" :labelledby="$id.'-label'" />
</div>
