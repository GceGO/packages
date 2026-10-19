{{--
    <x-gcego-forms::checkbox :binding="$binding" id="…" value="1" :checked="true" />
    A native checkbox (keyboard, forms and screen readers unchanged) drawn as a themed box with a check mark.
--}}
@props(['binding' => null, 'checked' => false])
<span class="{{ implode(' ', \Gcego\Forms\Presets\FormPresets::ChoiceBox) }}">
    <input type="checkbox" {{ $binding }} @if ($checked) checked @endif {{ $attributes->class(\Gcego\Forms\Presets\FormPresets::Choice) }}>
    <x-gcego-icon name="heroicon:check" class="pointer-events-none relative size-3 scale-50 text-primary-foreground opacity-0 transition duration-150 peer-checked:scale-100 peer-checked:opacity-100" :stroke-width="3.5" decorative />
</span>
