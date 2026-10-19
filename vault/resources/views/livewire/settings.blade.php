{{-- gcego-vault::livewire.settings — the effective configuration (read-only). --}}
<div class="{{ implode(' ', $p::Screen) }}" data-vault-screen="settings">
    <x-gcego-vault::header :title="__('Vault settings')" :lead="__('Read-only. Change config/gcego-vault.php and deploy; unsafe values are rejected when the application starts.')" :scope="$scopeLabel" />
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        @foreach ($sections as $heading => $values)
            <section class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }}" aria-label="{{ $heading }}">
                <h2 class="{{ implode(' ', $p::CardTitle) }}">{{ $heading }}</h2>
                <dl class="mt-3 divide-y divide-gcego-border text-sm">
                    @foreach ($values as $label => $value)
                        <div class="flex flex-col gap-1 py-2 sm:flex-row sm:justify-between"><dt class="text-gcego-text-muted">{{ $label }}</dt><dd class="break-all font-medium text-gcego-text sm:text-right">{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </section>
        @endforeach
    </div>
</div>
