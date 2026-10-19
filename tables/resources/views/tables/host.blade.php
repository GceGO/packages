<div data-gcego-table-host="{{ $definition }}" @if ($poll) wire:poll.{{ $poll }}s.visible="refresh" @endif>
    @once
        {{-- The listbox and date-picker controls, shared with gcego/forms (gcego/chroma resources/js/controls.js). --}}
        <x-gcego-chroma::controls />
    @endonce
    {{ $content }}
</div>
