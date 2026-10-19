{{-- gcego-vault::livewire.audit — the access audit log of this scope. --}}
<div class="{{ implode(' ', $p::Screen) }}" data-vault-screen="audit">
    <x-gcego-vault::header :title="__('Audit log')" :lead="__('Every change to roles, permissions and access, with who made it and why. Entries cannot be edited.')" :scope="$scopeLabel">
        <x-slot:actions><button type="button" wire:click="export" class="{{ $s->button('secondary') }}">{{ __('Export CSV') }}</button></x-slot:actions>
    </x-gcego-vault::header>

    <section class="{{ implode(' ', $p::Card) }}" aria-label="{{ __('Audit entries') }}">
        <div class="{{ implode(' ', $p::Toolbar) }} flex-wrap">
            <div class="flex w-full flex-col gap-3 sm:flex-row sm:flex-wrap">
                <label class="sr-only" for="vault-audit-event">{{ __('Event') }}</label>
                <select id="vault-audit-event" wire:model.live="event" class="{{ $s->input('sm:w-56') }}"><option value="">{{ __('Every event') }}</option>@foreach ($events as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach</select>
                <label class="sr-only" for="vault-audit-search">{{ __('Search') }}</label>
                <input id="vault-audit-search" type="search" wire:model.live.debounce.300ms="search" maxlength="100" placeholder="{{ __('Person, role or permission') }}" class="{{ $s->input('sm:max-w-xs') }}">
                <label class="sr-only" for="vault-audit-from">{{ __('From') }}</label>
                <input id="vault-audit-from" type="date" wire:model.live="from" class="{{ $s->input('sm:w-40') }}">
                <label class="sr-only" for="vault-audit-to">{{ __('To') }}</label>
                <input id="vault-audit-to" type="date" wire:model.live="to" class="{{ $s->input('sm:w-40') }}">
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="{{ implode(' ', $p::Table) }}">
                <thead><tr>
                    <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('When') }}</th>
                    <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Event') }}</th>
                    <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('By') }}</th>
                    <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Person') }}</th>
                    <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('What') }}</th>
                    <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Reason') }}</th>
                </tr></thead>
                <tbody class="divide-y divide-gcego-border">
                    @forelse ($entries as $entry)
                        <tr class="{{ implode(' ', $p::Row) }}" wire:key="audit-{{ $entry->id }}">
                            <td class="{{ implode(' ', $p::Td) }} whitespace-nowrap text-gcego-text-muted"><time datetime="{{ $entry->created_at?->toIso8601String() }}">{{ $entry->created_at?->toDayDateTimeString() }}</time></td>
                            <td class="{{ implode(' ', $p::Td) }}">@include('gcego-vault::partials.badge', ['tone' => $entry->event->tone(), 'text' => $entry->event->label()])@if (isset($entry->payload['rule'])) <span class="block text-xs text-gcego-text-muted">{{ $entry->payload['rule'] }}</span>@endif</td>
                            <td class="{{ implode(' ', $p::Td) }}">{{ $entry->actor_name ?? ($entry->actor_id === null ? __('System') : '#'.$entry->actor_id) }}</td>
                            <td class="{{ implode(' ', $p::Td) }}">{{ $entry->subject_name ?? ($entry->subject_id === null ? '—' : '#'.$entry->subject_id) }}</td>
                            <td class="{{ implode(' ', $p::Td) }} break-all font-mono text-xs">{{ $entry->target ?? '—' }}</td>
                            <td class="{{ implode(' ', $p::Td) }} text-gcego-text-muted">{{ $entry->reason ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="{{ implode(' ', $p::Empty) }}">{{ __('No entry matches.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gcego-border p-4">{{ $entries->links() }}</div>
    </section>
</div>
