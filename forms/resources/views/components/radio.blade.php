{{--
    <x-gcego-forms::radio :binding="$binding" id="…" value="a" :checked="true" />
    A native radio button drawn as a themed circle with an inner dot.
--}}
@props(['binding' => null, 'checked' => false])
<span class="{{ implode(' ', \Gcego\Forms\Presets\FormPresets::ChoiceBox) }}">
    <input type="radio" {{ $binding }} @if ($checked) checked @endif {{ $attributes->class(\Gcego\Forms\Presets\FormPresets::Radio) }}>
    <span class="pointer-events-none relative size-2 scale-0 rounded-full bg-primary-foreground transition duration-150 peer-checked:scale-100" aria-hidden="true"></span>
</span>
