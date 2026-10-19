@php($choices = ['1' => $filter->stateLabel(true)] + ($filter instanceof \Gcego\Tables\Filters\CustomFilter ? [] : ['0' => $filter->stateLabel(false)]))
<div class="flex flex-col gap-1.5" data-gcego-filter="{{ $name }}">
    <span id="{{ $id }}-label" class="{{ implode(' ', $presets::FilterLabel) }}">{{ __($filter->label()) }}</span>
    <x-gcego-tables::listbox :id="$id" :binding="$binding" :options="$choices" :value="$state === null ? null : ($state ? '1' : '0')" :labelledby="$id.'-label'" />
</div>
