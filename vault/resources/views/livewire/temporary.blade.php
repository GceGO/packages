{{-- gcego-vault::livewire.temporary — time-bound access. --}}
<div class="{{ implode(' ', $p::Screen) }}" data-vault-screen="temporary">
    <x-gcego-vault::header :title="__('Temporary access')" :lead="__('Access with an end date stops working at that moment, even before any cleanup runs.')" :scope="$scopeLabel" />

    @include('gcego-vault::partials.feedback')

    @foreach (['active' => [__('Active'), $active], 'expired' => [__('Expired in the last 30 days'), $expired]] as $key => [$heading, $records])
        <section class="{{ implode(' ', $p::Card) }}" aria-labelledby="vault-temp-{{ $key }}">
            <div class="{{ implode(' ', $p::Toolbar) }}"><h2 id="vault-temp-{{ $key }}" class="{{ implode(' ', $p::CardTitle) }}">{{ $heading }} <span class="text-sm font-normal text-gcego-text-muted">({{ $records->count() }})</span></h2></div>
            <div class="overflow-x-auto">
                <table class="{{ implode(' ', $p::Table) }}">
                    <thead><tr>
                        <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Person') }}</th>
                        <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ __('Access') }}</th>
                        <th scope="col" class="{{ implode(' ', $p::Th) }}">{{ $key === 'active' ? __('Ends') : __('Ended') }}</th>
                        <th scope="col" class="{{ implode(' ', $p::Th) }}"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr></thead>
                    <tbody class="divide-y divide-gcego-border">
                        @forelse ($records as $record)
                            @php($kind = $record instanceof \Gcego\Vault\Models\Grant ? 'grant' : 'role')
                            <tr class="{{ implode(' ', $p::Row) }}" wire:key="{{ $key }}-{{ $kind }}-{{ $record->id }}">
                                <td class="{{ implode(' ', $p::Td) }}">
                                    @php($url = $record->holder === null ? null : $this->personUrl($record->holder))
                                    @if ($url)<a href="{{ $url }}" class="{{ implode(' ', $p::Link) }}">{{ $record->holder->getAttribute('name') }}</a>@else{{ '#'.$record->model_id }}@endif
                                </td>
                                <td class="{{ implode(' ', $p::Td) }}">{{ $kind === 'role' ? __('Role :role', ['role' => $record->role?->label]) : $record->permission?->name }}@if ($record->source === 'request') @include('gcego-vault::partials.badge', ['tone' => 'info', 'text' => __('Via request')])@endif</td>
                                <td class="{{ implode(' ', $p::Td) }} whitespace-nowrap"><time datetime="{{ $record->expires_at?->toIso8601String() }}">{{ $record->expires_at?->toDayDateTimeString() }}</time> <span class="text-xs text-gcego-text-muted">({{ $record->expires_at?->diffForHumans() }})</span></td>
                                <td class="{{ implode(' ', $p::Td) }} text-right">@if ($key === 'active' && $canEnd)<button type="button" wire:click="confirmRevoke('{{ $kind }}', {{ $record->id }})" class="{{ $s->button('destructive', 'text-xs') }}">{{ __('End now') }}</button>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="{{ implode(' ', $p::Empty) }}">{{ __('Nothing here.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    <x-gcego-vault::confirm :show="$revoking !== null" :title="__('End this access now?')" :confirm="__('End now')" action="revoke" :danger="true">{{ __('It stops working immediately.') }}</x-gcego-vault::confirm>
</div>
