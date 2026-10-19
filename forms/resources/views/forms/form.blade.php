<div class="{{ $styles('root') }} flex flex-col gap-6" data-gcego-form="{{ $component->formName() }}">
    @once
        {{-- The listbox and date / time picker controls, shared with gcego/tables (gcego/chroma resources/js/controls.js). --}}
        <x-gcego-chroma::controls />
    @endonce
    {{ $children }}
</div>
