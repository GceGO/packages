{{-- <x-gcego-vault::confirm> — an in-page confirmation dialog (never a browser dialog). Shown while $show is true. --}}
@props(['show' => false, 'title', 'confirm' => 'Confirm', 'action', 'cancel' => 'cancelConfirm', 'danger' => false])
@php($p = \Gcego\Vault\Presets\VaultPresets::class)
@if ($show)
    <div class="{{ implode(' ', $p::Dialog) }}" role="dialog" aria-modal="true" aria-labelledby="vault-confirm-title" x-data x-init="$nextTick(() => $el.querySelector('[data-autofocus]')?.focus())" x-on:keydown.escape.window="$wire.{{ $cancel }}()">
        <div class="{{ implode(' ', $p::DialogPanel) }}">
            <h2 id="vault-confirm-title" class="text-lg font-semibold text-gcego-text">{{ $title }}</h2>
            <div class="mt-2 text-sm text-gcego-text-muted">{{ $slot }}</div>
            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" wire:click="{{ $cancel }}" class="{{ app(\Gcego\Vault\Support\VaultStyles::class)->button('secondary') }}">{{ __('Cancel') }}</button>
                <button type="button" wire:click="{{ $action }}" data-autofocus class="{{ app(\Gcego\Vault\Support\VaultStyles::class)->button($danger ? 'destructive' : 'primary') }}">{{ $confirm }}</button>
            </div>
        </div>
    </div>
@endif
