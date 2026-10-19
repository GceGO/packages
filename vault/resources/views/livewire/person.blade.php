{{-- gcego-vault::livewire.person — one person's access and its reasons. --}}
<div class="{{ implode(' ', $p::Screen) }}" data-vault-screen="person">
    <x-gcego-vault::header :title="$person->getAttribute('name') ?? '#'.$person->getKey()" :lead="$person->getAttribute('email')" :scope="$scopeLabel">
        <x-slot:actions><a href="{{ $this->url('people') }}" class="{{ $s->button('secondary') }}">{{ __('All people') }}</a></x-slot:actions>
    </x-gcego-vault::header>

    @include('gcego-vault::partials.feedback')

    <dl class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="{{ implode(' ', $p::Stat) }}"><dt class="{{ implode(' ', $p::StatLabel) }}">{{ __('Roles') }}</dt><dd class="{{ implode(' ', $p::StatValue) }}">{{ count($effective->roles) }}</dd></div>
        <div class="{{ implode(' ', $p::Stat) }}"><dt class="{{ implode(' ', $p::StatLabel) }}">{{ __('Inherited roles') }}</dt><dd class="{{ implode(' ', $p::StatValue) }}">{{ count($effective->inherited) }}</dd></div>
        <div class="{{ implode(' ', $p::Stat) }}"><dt class="{{ implode(' ', $p::StatLabel) }}">{{ __('Permissions') }}</dt><dd class="{{ implode(' ', $p::StatValue) }}">{{ count($effective->names()) }}</dd></div>
        <div class="{{ implode(' ', $p::Stat) }}"><dt class="{{ implode(' ', $p::StatLabel) }}">{{ __('Next expiry') }}</dt><dd class="mt-2 text-sm text-gcego-text">{{ $effective->expiresAt === null ? __('None') : \Illuminate\Support\Carbon::createFromTimestamp($effective->expiresAt)->toDayDateTimeString() }}</dd></div>
    </dl>
    @if ($effective->super)<p class="{{ implode(' ', [...$p::Notice, ...$p::NoticeTones['info']]) }}">{{ __('A super-administrator role applies: every registered global permission is allowed, except those excluded by configuration. Policies still decide record conditions.') }}</p>@endif

    <div role="tablist" aria-label="{{ __('Access views') }}" class="{{ implode(' ', $p::Tabs) }}">
        @foreach ($tabs as $view)
            <button type="button" role="tab" id="vault-ptab-{{ $view }}" aria-controls="vault-ppanel-{{ $view }}" aria-selected="{{ $tab === $view ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $view }}')" class="{{ implode(' ', [...$p::Tab, ...($tab === $view ? $p::TabActive : $p::TabIdle)]) }}">{{ ['access' => __('Roles and grants'), 'effective' => __('Effective permissions'), 'history' => __('History')][$view] }}</button>
        @endforeach
    </div>

    <div id="vault-ppanel-{{ $tab }}" role="tabpanel" aria-labelledby="vault-ptab-{{ $tab }}" class="flex flex-col gap-6">
        @if ($tab === 'access')
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <section class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }}" aria-labelledby="vault-held-roles">
                    <h2 id="vault-held-roles" class="{{ implode(' ', $p::CardTitle) }}">{{ __('Roles held') }}</h2>
                    <ul class="mt-3 divide-y divide-gcego-border">
                        @forelse ($assignments as $assignment)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2" wire:key="a-{{ $assignment->id }}">
                                <span class="min-w-0">
                                    <a href="{{ $this->url('roles.show', ['role' => $assignment->role_id]) }}" class="{{ implode(' ', $p::Link) }}">{{ $assignment->role?->label }}</a>
                                    <span class="block text-xs text-gcego-text-muted">{{ __('Given by :who on :date', ['who' => $assignment->grantor?->getAttribute('name') ?? __('System'), 'date' => $assignment->created_at?->toFormattedDateString()]) }}@if ($assignment->expires_at) · {{ __('until :date', ['date' => $assignment->expires_at->toDayDateTimeString()]) }}@endif</span>
                                </span>
                                @if ($can['assign'])<button type="button" wire:click="confirmRevoke('role', {{ $assignment->id }})" class="{{ $s->button('destructive', 'text-xs') }}">{{ __('Revoke') }}</button>@endif
                            </li>
                        @empty
                            <li class="py-2 text-sm text-gcego-text-muted">{{ __('No roles here.') }}</li>
                        @endforelse
                    </ul>
                    @if ($effective->inherited !== [])<p class="mt-3 text-xs text-gcego-text-muted">{{ __('Through inheritance also: :roles', ['roles' => implode(', ', $effective->inherited)]) }}</p>@endif
                </section>
                <section class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }}" aria-labelledby="vault-direct-grants">
                    <h2 id="vault-direct-grants" class="{{ implode(' ', $p::CardTitle) }}">{{ __('Direct permissions') }}</h2>
                    <ul class="mt-3 divide-y divide-gcego-border">
                        @forelse ($grants as $grantRecord)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2" wire:key="g-{{ $grantRecord->id }}">
                                <span class="min-w-0"><span class="font-mono text-sm text-gcego-text">{{ $grantRecord->permission?->name }}</span>
                                    <span class="block text-xs text-gcego-text-muted">{{ $grantRecord->reason ?? '' }}@if ($grantRecord->expires_at) · {{ __('until :date', ['date' => $grantRecord->expires_at->toDayDateTimeString()]) }}@endif</span>
                                </span>
                                @if ($can['grant'])<button type="button" wire:click="confirmRevoke('grant', {{ $grantRecord->id }})" class="{{ $s->button('destructive', 'text-xs') }}">{{ __('Revoke') }}</button>@endif
                            </li>
                        @empty
                            <li class="py-2 text-sm text-gcego-text-muted">{{ __('No direct permissions.') }}</li>
                        @endforelse
                    </ul>
                </section>
            </div>

            @if ($can['assign'] || $can['grant'])
                <section class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }}" aria-labelledby="vault-give-access">
                    <h2 id="vault-give-access" class="{{ implode(' ', $p::CardTitle) }}">{{ __('Give access') }}</h2>
                    <p class="{{ implode(' ', $p::Lead) }}">{{ __('You can only give access you hold yourself, and never to yourself.') }}</p>
                    <div class="mt-4 {{ implode(' ', $p::Grid) }}">
                        <div>
                            <label for="vault-give-until" class="{{ implode(' ', $p::Label) }}">{{ __('Until (optional)') }}</label>
                            <input id="vault-give-until" type="date" wire:model="until" min="{{ now()->addDay()->toDateString() }}" @if ($maxDate) max="{{ $maxDate }}" @endif class="{{ $s->input() }}">
                            @error('until')<p class="{{ implode(' ', $p::Error) }}">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="vault-give-reason" class="{{ implode(' ', $p::Label) }}">{{ __('Reason') }}</label>
                            <input id="vault-give-reason" type="text" wire:model="reason" maxlength="500" class="{{ $s->input() }}">
                        </div>
                        @if ($can['assign'])
                            <form wire:submit="assignRole" class="flex flex-col gap-2">
                                <label for="vault-give-role" class="{{ implode(' ', $p::Label) }}">{{ __('Role') }}</label>
                                <div class="flex gap-2">
                                    <select id="vault-give-role" wire:model="role" class="{{ $s->input() }}"><option value="">{{ __('Choose a role') }}</option>@foreach ($roleOptions as $option)<option value="{{ $option->id }}">{{ $option->label }}</option>@endforeach</select>
                                    <button type="submit" class="{{ $s->button('primary') }}">{{ __('Assign') }}</button>
                                </div>
                                @error('role')<p class="{{ implode(' ', $p::Error) }}">{{ $message }}</p>@enderror
                            </form>
                        @endif
                        @if ($can['grant'])
                            <form wire:submit="grantPermission" class="flex flex-col gap-2">
                                <label for="vault-give-permission" class="{{ implode(' ', $p::Label) }}">{{ __('Direct permission') }}</label>
                                <div class="flex gap-2">
                                    <input id="vault-give-permission" type="text" wire:model="grant" list="vault-give-permissions" maxlength="150" class="{{ $s->input('font-mono') }}" placeholder="reports.export">
                                    <datalist id="vault-give-permissions">@foreach ($permissionOptions as $option)<option value="{{ $option->name }}">{{ $option->label }}</option>@endforeach</datalist>
                                    <button type="submit" class="{{ $s->button('primary') }}">{{ __('Grant') }}</button>
                                </div>
                                @error('grant')<p class="{{ implode(' ', $p::Error) }}">{{ $message }}</p>@enderror
                            </form>
                        @endif
                    </div>
                </section>
            @endif
        @elseif ($tab === 'effective')
            <section class="{{ implode(' ', $p::Card) }}" aria-label="{{ __('Effective permissions') }}">
                <div class="{{ implode(' ', $p::Toolbar) }}">
                    <label class="sr-only" for="vault-eff-filter">{{ __('Filter') }}</label>
                    <input id="vault-eff-filter" type="search" wire:model.live.debounce.250ms="filter" maxlength="100" placeholder="{{ __('Filter permissions') }}" class="{{ $s->input('sm:max-w-xs') }}">
                </div>
                <div class="overflow-x-auto">
                    <table class="{{ implode(' ', $p::Table) }}">
                        <thead><tr><th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Permission') }}</th><th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Granted by') }}</th></tr></thead>
                        <tbody class="divide-y divide-gcego-border">
                            @forelse ($permissions as $row)
                                <tr><td class="{{ implode(' ', $p::Td) }}">{{ $row['label'] }} <span class="block font-mono text-xs text-gcego-text-muted">{{ $row['name'] }}</span></td><td class="{{ implode(' ', $p::Td) }} text-xs text-gcego-text-muted">{{ implode(', ', $row['sources']) }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="{{ implode(' ', $p::Empty) }}">{{ __('No permission here. Nothing is allowed by default.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @else
            <section class="{{ implode(' ', $p::Card) }}" aria-label="{{ __('History') }}">
                <div class="overflow-x-auto">
                    <table class="{{ implode(' ', $p::Table) }}">
                        <thead><tr><th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Access') }}</th><th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Status') }}</th><th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Given') }}</th><th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Source') }}</th></tr></thead>
                        <tbody class="divide-y divide-gcego-border">
                            @forelse ($history as $record)
                                <tr>
                                    <td class="{{ implode(' ', $p::Td) }}">{{ $record instanceof \Gcego\Vault\Models\Assignment ? __('Role :role', ['role' => $record->role?->label]) : $record->permission?->name }}</td>
                                    <td class="{{ implode(' ', $p::Td) }}">@include('gcego-vault::partials.badge', ['tone' => ['active' => 'success', 'expired' => 'warning'][$record->status()] ?? 'neutral', 'text' => __('gcego-vault::messages.status.'.$record->status())])</td>
                                    <td class="{{ implode(' ', $p::Td) }} whitespace-nowrap text-gcego-text-muted">{{ $record->created_at?->toDayDateTimeString() }}</td>
                                    <td class="{{ implode(' ', $p::Td) }} text-gcego-text-muted">{{ $record->source }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="{{ implode(' ', $p::Empty) }}">{{ __('No history yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }}" aria-labelledby="vault-person-audit">
                <h2 id="vault-person-audit" class="{{ implode(' ', $p::CardTitle) }}">{{ __('Audit entries') }}</h2>
                <ol class="mt-3 space-y-2 text-sm">
                    @forelse ($audit as $entry)
                        <li class="flex flex-wrap items-center gap-2">@include('gcego-vault::partials.badge', ['tone' => $entry->event->tone(), 'text' => $entry->event->label()]) <span class="font-mono text-xs text-gcego-text">{{ $entry->target }}</span> <span class="text-gcego-text-muted">{{ $entry->actor_name ?? __('System') }} · {{ $entry->created_at?->diffForHumans() }}</span></li>
                    @empty
                        <li class="text-gcego-text-muted">{{ __('No entries.') }}</li>
                    @endforelse
                </ol>
            </section>
        @endif
    </div>

    <x-gcego-vault::confirm :show="$revoking !== null" :title="__('Revoke this access?')" :confirm="__('Revoke')" action="revoke" :danger="true">
        {{ __('It stops working immediately, on every device and worker.') }}
    </x-gcego-vault::confirm>
</div>
