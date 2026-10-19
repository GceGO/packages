{{-- gcego-vault::livewire.people — people and their roles in this scope. --}}
<div class="{{ implode(' ', $p::Screen) }}" data-vault-screen="people">
    <x-gcego-vault::header :title="__('People')" :lead="__('Who holds which roles here. Open a person to see everything they may do and why.')" :scope="$scopeLabel" />

    <section class="{{ implode(' ', $p::Card) }}" aria-label="{{ __('People') }}">
        <div class="{{ implode(' ', $p::Toolbar) }}">
            <label class="sr-only" for="vault-people-search">{{ __('Search people') }}</label>
            <input id="vault-people-search" type="search" wire:model.live.debounce.300ms="search" maxlength="100" placeholder="{{ __('Search everyone by name or email') }}" class="{{ $s->input('sm:max-w-sm') }}">
            <p class="text-sm text-gcego-text-muted">{{ trim($search) === '' ? __('Showing people who hold access here.') : __('Search results') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="{{ implode(' ', $p::Table) }}">
                <thead><tr>
                    <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Person') }}</th>
                    <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Roles') }}</th>
                    <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Permissions') }}</th>
                </tr></thead>
                <tbody class="divide-y divide-gcego-border">
                    @forelse ($rows as $row)
                        <tr class="{{ implode(' ', $p::Row) }}" wire:key="person-{{ $row['reference'] }}">
                            <td class="{{ implode(' ', $p::Td) }}">
                                <a href="{{ $this->url('people.show', ['holder' => $row['reference']]) }}" class="{{ implode(' ', $p::Link) }}">{{ $row['person']->getAttribute('name') ?? '#'.$row['person']->getKey() }}</a>
                                <span class="block text-xs text-gcego-text-muted">{{ $row['person']->getAttribute('email') }}</span>
                            </td>
                            <td class="{{ implode(' ', $p::Td) }}">
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($row['roles'] as $roleName)@include('gcego-vault::partials.badge', ['tone' => 'primary', 'text' => $roleName])@empty<span class="text-gcego-text-muted">{{ __('None') }}</span>@endforelse
                                    @if ($row['super'])@include('gcego-vault::partials.badge', ['tone' => 'danger', 'text' => __('Super administrator')])@endif
                                    @if ($row['temporary'] !== null)@include('gcego-vault::partials.badge', ['tone' => 'warning', 'text' => __('Temporary access')])@endif
                                </div>
                            </td>
                            <td class="{{ implode(' ', $p::Td) }} tabular-nums">{{ $row['count'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="{{ implode(' ', $p::Empty) }}">{{ trim($search) === '' ? __('Nobody holds access here yet. Search for a person to give them a role.') : __('Nobody matches.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gcego-border p-4">{{ $people->links() }}</div>
    </section>
</div>
