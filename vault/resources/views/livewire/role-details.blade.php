{{-- gcego-vault::livewire.role-details — one role, its members, permissions and history. --}}
<div class="{{ implode(' ', $p::Screen) }}" data-vault-screen="role-details">
    <x-gcego-vault::header :title="$role->label" :lead="$role->description" :scope="$scopeLabel">
        <x-slot:actions>
            <a href="{{ $this->url('roles') }}" class="{{ $s->button('secondary') }}">{{ __('All roles') }}</a>
            @if ($can['edit'])<a href="{{ $this->url('roles.edit', ['role' => $role->id]) }}" class="{{ $s->button('primary') }}">{{ __('Edit') }}</a>@endif
        </x-slot:actions>
    </x-gcego-vault::header>

    @include('gcego-vault::partials.feedback')

    <dl class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="{{ implode(' ', $p::Stat) }}"><dt class="{{ implode(' ', $p::StatLabel) }}">{{ __('Identifier') }}</dt><dd class="mt-1 break-all font-mono text-sm text-gcego-text">{{ $role->name }}</dd></div>
        <div class="{{ implode(' ', $p::Stat) }}"><dt class="{{ implode(' ', $p::StatLabel) }}">{{ __('Holders here') }}</dt><dd class="{{ implode(' ', $p::StatValue) }}">{{ $members->total() }}</dd></div>
        <div class="{{ implode(' ', $p::Stat) }}"><dt class="{{ implode(' ', $p::StatLabel) }}">{{ __('Own permissions') }}</dt><dd class="{{ implode(' ', $p::StatValue) }}">{{ $role->permissions->count() }}</dd></div>
        <div class="{{ implode(' ', $p::Stat) }}"><dt class="{{ implode(' ', $p::StatLabel) }}">{{ __('Status') }}</dt>
            <dd class="mt-2 flex flex-wrap gap-1">
                @include('gcego-vault::partials.badge', $role->archived_at === null ? ['tone' => 'success', 'text' => __('Active')] : ['tone' => 'neutral', 'text' => __('Archived')])
                @if ($role->is_system)@include('gcego-vault::partials.badge', ['tone' => 'info', 'text' => __('System')])@endif
                @if ($role->is_protected)@include('gcego-vault::partials.badge', ['tone' => 'warning', 'text' => __('Protected')])@endif
                @if ($role->is_super)@include('gcego-vault::partials.badge', ['tone' => 'danger', 'text' => __('Super administrator')])@endif
                @if ($role->panel !== null)@include('gcego-vault::partials.badge', ['tone' => 'neutral', 'text' => __('Panel: :panel', ['panel' => $role->panel])])@endif
            </dd>
        </div>
    </dl>

    @if ($role->parents->isNotEmpty() || $role->children->isNotEmpty())
        <section class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }} text-sm text-gcego-text" aria-label="{{ __('Inheritance') }}">
            @if ($role->parents->isNotEmpty())
                <p>{{ __('Inherits') }}: @foreach ($role->parents as $parent)<a href="{{ $this->url('roles.show', ['role' => $parent->id]) }}" class="{{ implode(' ', $p::Link) }}">{{ $parent->label }}</a>@if (! $loop->last), @endif @endforeach</p>
            @endif
            @if ($role->children->isNotEmpty())
                <p class="mt-1">{{ __('Inherited by') }}: @foreach ($role->children as $child)<a href="{{ $this->url('roles.show', ['role' => $child->id]) }}" class="{{ implode(' ', $p::Link) }}">{{ $child->label }}</a>@if (! $loop->last), @endif @endforeach</p>
            @endif
        </section>
    @endif

    <div role="tablist" aria-label="{{ __('Role views') }}" class="{{ implode(' ', $p::Tabs) }}">
        @foreach ($tabs as $view)
            <button type="button" role="tab" id="vault-tab-{{ $view }}" aria-controls="vault-panel-{{ $view }}" aria-selected="{{ $tab === $view ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $view }}')" class="{{ implode(' ', [...$p::Tab, ...($tab === $view ? $p::TabActive : $p::TabIdle)]) }}">{{ ['members' => __('Members'), 'permissions' => __('Permissions'), 'history' => __('History')][$view] }}</button>
        @endforeach
    </div>

    <div id="vault-panel-{{ $tab }}" role="tabpanel" aria-labelledby="vault-tab-{{ $tab }}" class="flex flex-col gap-6">
        @if ($tab === 'members')
            @if ($can['assign'])
                <section class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }}" aria-labelledby="vault-assign-title">
                    <h2 id="vault-assign-title" class="{{ implode(' ', $p::CardTitle) }}">{{ __('Give this role to someone') }}</h2>
                    <form wire:submit="assign" class="mt-4 {{ implode(' ', $p::Grid) }}">
                        <div class="md:col-span-2">
                            @if ($picked !== null)
                                <p class="text-sm text-gcego-text">{{ __('Person') }}: <strong>{{ $picked->getAttribute('name') ?? $picked->getKey() }}</strong> <button type="button" wire:click="$set('personRef', null)" class="{{ implode(' ', $p::Link) }} ml-2 text-xs">{{ __('Change') }}</button></p>
                            @else
                                <label for="vault-assign-search" class="{{ implode(' ', $p::Label) }}">{{ __('Person') }}</label>
                                <input id="vault-assign-search" type="search" wire:model.live.debounce.300ms="personSearch" maxlength="100" autocomplete="off" placeholder="{{ __('Search by name or email') }}" class="{{ $s->input() }}" aria-controls="vault-assign-results">
                                <ul id="vault-assign-results" class="mt-2 divide-y divide-gcego-border rounded-gcego border border-gcego-border empty:hidden">
                                    @foreach ($results as $result)
                                        <li><button type="button" wire:click="pick('{{ $this->reference($result) }}')" class="w-full px-3 py-2 text-left text-sm text-gcego-text hover:bg-gcego-surface-muted">{{ $result->getAttribute('name') }} <span class="text-gcego-text-muted">{{ $result->getAttribute('email') }}</span></button></li>
                                    @endforeach
                                </ul>
                            @endif
                            @error('person')<p class="{{ implode(' ', $p::Error) }}">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="vault-assign-until" class="{{ implode(' ', $p::Label) }}">{{ __('Until (optional)') }}</label>
                            <input id="vault-assign-until" type="date" wire:model="until" min="{{ now()->addDay()->toDateString() }}" @if ($maxDate) max="{{ $maxDate }}" @endif class="{{ $s->input() }}" aria-describedby="vault-assign-until-hint">
                            <p id="vault-assign-until-hint" class="{{ implode(' ', $p::Hint) }}">{{ __('Temporary access ends automatically at the end of that day.') }}</p>
                            @error('until')<p class="{{ implode(' ', $p::Error) }}">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="vault-assign-reason" class="{{ implode(' ', $p::Label) }}">{{ __('Reason') }}</label>
                            <input id="vault-assign-reason" type="text" wire:model="reason" maxlength="500" class="{{ $s->input() }}">
                        </div>
                        <div class="md:col-span-2 flex justify-end"><button type="submit" class="{{ $s->button('primary') }}">{{ __('Assign role') }}</button></div>
                    </form>
                </section>
            @endif

            <section class="{{ implode(' ', $p::Card) }}" aria-label="{{ __('Members') }}">
                @if ($members->isEmpty())
                    <div class="{{ implode(' ', $p::Empty) }}"><p>{{ __('Nobody holds this role here.') }}</p></div>
                @else
                    <div class="overflow-x-auto">
                        <table class="{{ implode(' ', $p::Table) }}">
                            <thead><tr>
                                <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Person') }}</th>
                                <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Since') }}</th>
                                <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Until') }}</th>
                                <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Given by') }}</th>
                                <th scope="col" class="{{ implode(' ', $p::Th) }}"><span class="sr-only">{{ __('Actions') }}</span></th>
                            </tr></thead>
                            <tbody class="divide-y divide-gcego-border">
                                @foreach ($members as $assignment)
                                    <tr class="{{ implode(' ', $p::Row) }}" wire:key="member-{{ $assignment->id }}">
                                        <td class="{{ implode(' ', $p::Td) }}">
                                            @php($url = $assignment->holder === null ? null : $this->personUrl($assignment->holder))
                                            @if ($url)<a href="{{ $url }}" class="{{ implode(' ', $p::Link) }}">{{ $assignment->holder?->getAttribute('name') ?? '#'.$assignment->model_id }}</a>@else{{ '#'.$assignment->model_id }}@endif
                                            @if ($assignment->source === 'request')@include('gcego-vault::partials.badge', ['tone' => 'info', 'text' => __('Via request')])@endif
                                        </td>
                                        <td class="{{ implode(' ', $p::Td) }} whitespace-nowrap text-gcego-text-muted">{{ $assignment->created_at?->toFormattedDateString() }}</td>
                                        <td class="{{ implode(' ', $p::Td) }} whitespace-nowrap">@if ($assignment->expires_at)@include('gcego-vault::partials.badge', ['tone' => 'warning', 'text' => __('Until :date', ['date' => $assignment->expires_at->toFormattedDateString()])])@else<span class="text-gcego-text-muted">{{ __('Permanent') }}</span>@endif</td>
                                        <td class="{{ implode(' ', $p::Td) }} text-gcego-text-muted">{{ $assignment->grantor?->getAttribute('name') ?? __('System') }}</td>
                                        <td class="{{ implode(' ', $p::Td) }} text-right">@if ($can['assign'])<button type="button" wire:click="confirmRevoke({{ $assignment->id }})" class="{{ $s->button('destructive', 'text-xs') }}">{{ __('Revoke') }}</button>@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-gcego-border p-4">{{ $members->links() }}</div>
                @endif
            </section>
        @elseif ($tab === 'permissions')
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <section class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }}" aria-labelledby="vault-own-title">
                    <h2 id="vault-own-title" class="{{ implode(' ', $p::CardTitle) }}">{{ __('Own permissions') }}</h2>
                    @forelse ($own as $groupName => $items)
                        <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-gcego-text-muted">{{ $groupName === '' ? __('Other') : $groupName }}</h3>
                        <ul class="mt-1 space-y-1 text-sm text-gcego-text">@foreach ($items as $permission)<li>{{ $permission->label }} <span class="font-mono text-xs text-gcego-text-muted">{{ $permission->name }}</span></li>@endforeach</ul>
                    @empty
                        <p class="mt-2 text-sm text-gcego-text-muted">{{ __('None.') }}</p>
                    @endforelse
                </section>
                <section class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }}" aria-labelledby="vault-inherited-title">
                    <h2 id="vault-inherited-title" class="{{ implode(' ', $p::CardTitle) }}">{{ __('Inherited permissions') }}</h2>
                    @forelse ($inherited as $groupName => $items)
                        <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-gcego-text-muted">{{ $groupName === '' ? __('Other') : $groupName }}</h3>
                        <ul class="mt-1 space-y-1 text-sm text-gcego-text">@foreach ($items as $permission)<li>{{ $permission['label'] }} <span class="font-mono text-xs text-gcego-text-muted">{{ $permission['name'] }}</span> <span class="text-xs text-gcego-text-muted">{{ __('from :role', ['role' => $permission['from']]) }}</span></li>@endforeach</ul>
                    @empty
                        <p class="mt-2 text-sm text-gcego-text-muted">{{ __('None.') }}</p>
                    @endforelse
                </section>
            </div>
        @else
            <section class="{{ implode(' ', $p::Card) }}" aria-label="{{ __('Assignment history') }}">
                <div class="overflow-x-auto">
                    <table class="{{ implode(' ', $p::Table) }}">
                        <thead><tr>
                            <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Person') }}</th>
                            <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Status') }}</th>
                            <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Given') }}</th>
                            <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Ended') }}</th>
                            <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Reason') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gcego-border">
                            @forelse ($history as $assignment)
                                <tr wire:key="history-{{ $assignment->id }}">
                                    <td class="{{ implode(' ', $p::Td) }}">{{ $assignment->holder?->getAttribute('name') ?? '#'.$assignment->model_id }}</td>
                                    <td class="{{ implode(' ', $p::Td) }}">@include('gcego-vault::partials.badge', ['tone' => ['active' => 'success', 'expired' => 'warning', 'revoked' => 'neutral'][$assignment->status()] ?? 'neutral', 'text' => __('gcego-vault::messages.status.'.$assignment->status())])</td>
                                    <td class="{{ implode(' ', $p::Td) }} whitespace-nowrap text-gcego-text-muted">{{ $assignment->created_at?->toDayDateTimeString() }}</td>
                                    <td class="{{ implode(' ', $p::Td) }} whitespace-nowrap text-gcego-text-muted">{{ ($assignment->revoked_at ?? ($assignment->status() === 'expired' ? $assignment->expires_at : null))?->toDayDateTimeString() ?? '—' }}</td>
                                    <td class="{{ implode(' ', $p::Td) }} text-gcego-text-muted">{{ $assignment->revoke_reason ?? $assignment->reason ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="{{ implode(' ', $p::Empty) }}">{{ __('No history yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-gcego-border p-4">{{ $history->links() }}</div>
            </section>
            <section class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }}" aria-labelledby="vault-role-audit">
                <h2 id="vault-role-audit" class="{{ implode(' ', $p::CardTitle) }}">{{ __('Changes to this role') }}</h2>
                <ol class="mt-3 space-y-2 text-sm">
                    @forelse ($audit as $entry)
                        <li class="flex flex-wrap items-center gap-2">@include('gcego-vault::partials.badge', ['tone' => $entry->event->tone(), 'text' => $entry->event->label()]) <span class="text-gcego-text">{{ $entry->actor_name ?? __('System') }}</span> <time class="text-gcego-text-muted" datetime="{{ $entry->created_at?->toIso8601String() }}">{{ $entry->created_at?->diffForHumans() }}</time></li>
                    @empty
                        <li class="text-gcego-text-muted">{{ __('No changes recorded.') }}</li>
                    @endforelse
                </ol>
            </section>
        @endif
    </div>

    <x-gcego-vault::confirm :show="$revokingAssignment !== null" :title="__('Revoke this role?')" :confirm="__('Revoke')" action="revoke" :danger="true">
        <p>{{ __(':person loses ":role" and every permission it gives, at once.', ['person' => $revokingAssignment?->holder?->getAttribute('name') ?? '', 'role' => $role->label]) }}</p>
        <label for="vault-revoke-reason" class="{{ implode(' ', $p::Label) }} mt-3">{{ __('Reason (optional)') }}</label>
        <input id="vault-revoke-reason" type="text" wire:model="revokeReason" maxlength="500" class="{{ $s->input() }}">
    </x-gcego-vault::confirm>
</div>
