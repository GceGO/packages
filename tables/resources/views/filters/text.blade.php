<div class="flex flex-col gap-1.5" data-gcego-filter="{{ $name }}">
    <label for="{{ $id }}" class="{{ implode(' ', $presets::FilterLabel) }}">{{ __($filter->label()) }}</label>
    <input id="{{ $id }}" type="text" maxlength="100" wire:model.live.debounce.400ms="{{ $binding }}" class="{{ implode(' ', $presets::FilterControl) }}">
</div>
