{{-- <x-gcego-vault::header title="..." lead="..." :scope="$scopeLabel"> with an optional actions slot. --}}
@props(['title', 'lead' => null, 'scope' => null])
@php($p = \Gcego\Vault\Presets\VaultPresets::class)
<header class="{{ implode(' ', $p::Header) }}">
    <div class="min-w-0">
        <p class="text-xs font-medium uppercase tracking-wide text-gcego-text-muted">{{ __('Access control') }}@if ($scope !== null) · <span data-vault-scope>{{ $scope }}</span>@endif</p>
        <h1 class="{{ implode(' ', $p::Title) }}">{{ $title }}</h1>
        @if ($lead !== null)<p class="{{ implode(' ', $p::Lead) }}">{{ $lead }}</p>@endif
    </div>
    @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
</header>
