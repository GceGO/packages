{{-- gcego-vault::livewire.diagnostics — explain decisions and check the installation. --}}
<div class="{{ implode(' ', $p::Screen) }}" data-vault-screen="diagnostics">
    <x-gcego-vault::header :title="__('Diagnostics')" :lead="__('Find out why someone may or may not do something, and whether Vault is set up correctly.')" :scope="$scopeLabel" />

    <section class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }}" aria-labelledby="vault-explain-title">
        <h2 id="vault-explain-title" class="{{ implode(' ', $p::CardTitle) }}">{{ __('Explain a decision') }}</h2>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                @if ($person !== null)
                    <p class="{{ implode(' ', $p::Label) }}">{{ __('Person') }}</p>
                    <p class="text-sm text-gcego-text">{{ $person->getAttribute('name') }} <button type="button" wire:click="$set('personRef', null)" class="{{ implode(' ', $p::Link) }} ml-2 text-xs">{{ __('Change') }}</button></p>
                @else
                    <label for="vault-diag-person" class="{{ implode(' ', $p::Label) }}">{{ __('Person') }}</label>
                    <input id="vault-diag-person" type="search" wire:model.live.debounce.300ms="personSearch" maxlength="100" autocomplete="off" placeholder="{{ __('Search by name or email') }}" class="{{ $s->input() }}">
                    <ul class="mt-2 divide-y divide-gcego-border rounded-gcego border border-gcego-border empty:hidden">
                        @foreach ($results as $result)<li><button type="button" wire:click="pick('{{ $this->reference($result) }}')" class="w-full px-3 py-2 text-left text-sm text-gcego-text hover:bg-gcego-surface-muted">{{ $result->getAttribute('name') }} <span class="text-gcego-text-muted">{{ $result->getAttribute('email') }}</span></button></li>@endforeach
                    </ul>
                @endif
            </div>
            <div>
                <label for="vault-diag-permission" class="{{ implode(' ', $p::Label) }}">{{ __('Permission') }}</label>
                <input id="vault-diag-permission" type="text" wire:model.live.debounce.300ms="check" list="vault-diag-permissions" maxlength="150" class="{{ $s->input('font-mono') }}" placeholder="subjects.delete">
                <datalist id="vault-diag-permissions">@foreach ($permissionNames as $name)<option value="{{ $name }}"></option>@endforeach</datalist>
            </div>
            <div>
                <label for="vault-diag-panel" class="{{ implode(' ', $p::Label) }}">{{ __('Inside panel') }}</label>
                <select id="vault-diag-panel" wire:model.live="panel" class="{{ $s->input() }}"><option value="">{{ __('Outside panels') }}</option>@foreach ($panels as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select>
            </div>
        </div>
        <div aria-live="polite" class="mt-4">
            @if ($explanation !== null)
                @php($allowed = $explanation->allowed)
                <div class="{{ implode(' ', [...$p::Notice, ...($allowed ? $p::NoticeTones['success'] : $p::NoticeTones['error'])]) }}" data-vault-decision="{{ $allowed ? 'allowed' : 'denied' }}">
                    <p class="font-semibold">{{ $allowed ? __('Decision: ALLOWED') : __('Decision: DENIED') }}</p>
                    <p class="mt-1 font-mono text-xs">{{ __('Ability') }}: {{ $explanation->permission }}</p>
                    <p class="mt-2">{{ $explanation->message() }}</p>
                    <p class="mt-2 text-xs text-gcego-text-muted">{{ __('This is Vault\'s permission answer. Policies may still add record conditions (ownership, state, membership).') }}</p>
                </div>
            @endif
        </div>
    </section>

    <dl class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
        @foreach (['roles' => __('Roles'), 'permissions' => __('Permissions'), 'assignments' => __('Assignments'), 'grants' => __('Direct grants'), 'temporary' => __('Temporary'), 'pending_requests' => __('Pending requests')] as $key => $label)
            <div class="{{ implode(' ', $p::Stat) }}"><dt class="{{ implode(' ', $p::StatLabel) }}">{{ $label }}</dt><dd class="{{ implode(' ', $p::StatValue) }}">{{ $report['counts'][$key] }}</dd></div>
        @endforeach
    </dl>

    <section class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }}" aria-labelledby="vault-health-title">
        <h2 id="vault-health-title" class="{{ implode(' ', $p::CardTitle) }}">{{ __('Health checks') }}</h2>
        <ul class="mt-3 space-y-2 text-sm">
            @foreach ($report['checks'] as $check)
                <li class="flex items-start gap-2">@include('gcego-vault::partials.badge', ['tone' => ['ok' => 'success', 'warning' => 'warning', 'error' => 'danger', 'info' => 'info'][$check['status']], 'text' => ['ok' => __('OK'), 'warning' => __('Warning'), 'error' => __('Problem'), 'info' => __('Note')][$check['status']]]) <span class="text-gcego-text">{{ $check['message'] }}</span></li>
            @endforeach
        </ul>
    </section>
</div>
