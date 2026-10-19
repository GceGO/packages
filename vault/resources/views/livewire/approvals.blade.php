{{-- gcego-vault::livewire.approvals — requests waiting for this reviewer. --}}
<div class="{{ implode(' ', $p::Screen) }}" data-vault-screen="approvals">
    <x-gcego-vault::header :title="__('Approvals')" :lead="__('You can approve only what you could give yourself. Your own requests never appear here.')" :scope="$scopeLabel" />

    @include('gcego-vault::partials.feedback')

    <div class="flex flex-col gap-4">
        @forelse ($requests as $request)
            <article class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }}" wire:key="approval-{{ $request->id }}" aria-labelledby="vault-approval-{{ $request->id }}">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 id="vault-approval-{{ $request->id }}" class="{{ implode(' ', $p::CardTitle) }}">{{ $request->role?->label ?? $request->permission?->name }} {{ __('for :person', ['person' => $request->subject?->getAttribute('name') ?? '#'.$request->subject_id]) }}</h2>
                        <p class="text-xs text-gcego-text-muted">{{ __('Asked by :who, :when', ['who' => $request->requester?->getAttribute('name') ?? '#'.$request->requester_id, 'when' => $request->created_at?->diffForHumans()]) }}@if ($request->requested_until) · {{ __('until :date', ['date' => $request->requested_until->toFormattedDateString()]) }}@endif</p>
                    </div>
                    <div class="flex flex-wrap gap-1">
                        @if ($request->is_sensitive)@include('gcego-vault::partials.badge', ['tone' => 'warning', 'text' => __('Sensitive')])@endif
                        @include('gcego-vault::partials.badge', ['tone' => 'info', 'text' => __(':done of :needed approvals', ['done' => $request->approvals->where('decision', 'approve')->count(), 'needed' => $request->approvals_required])])
                    </div>
                </div>
                <blockquote class="mt-3 border-l-2 border-gcego-border pl-3 text-sm text-gcego-text">{{ $request->reason }}</blockquote>
                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div class="md:col-span-2">
                        <label for="vault-note-{{ $request->id }}" class="{{ implode(' ', $p::Label) }}">{{ __('Note (required to reject)') }}</label>
                        <input id="vault-note-{{ $request->id }}" type="text" wire:model="notes.{{ $request->id }}" maxlength="500" class="{{ $s->input() }}">
                    </div>
                    <div>
                        <label for="vault-until-{{ $request->id }}" class="{{ implode(' ', $p::Label) }}">{{ __('Grant until') }}</label>
                        <input id="vault-until-{{ $request->id }}" type="date" wire:model="untils.{{ $request->id }}" min="{{ now()->addDay()->toDateString() }}" class="{{ $s->input() }}" aria-describedby="vault-until-hint-{{ $request->id }}">
                        <p id="vault-until-hint-{{ $request->id }}" class="{{ implode(' ', $p::Hint) }}">{{ __('Empty: as requested.') }}</p>
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="reject({{ $request->id }})" class="{{ $s->button('destructive') }}">{{ __('Reject') }}</button>
                    <button type="button" wire:click="approve({{ $request->id }})" class="{{ $s->button('primary') }}">{{ __('Approve') }}</button>
                </div>
            </article>
        @empty
            <div class="{{ implode(' ', [...$p::Card, ...$p::Empty]) }}"><p>{{ __('Nothing is waiting for you.') }}</p></div>
        @endforelse
        {{ $requests->links() }}
    </div>
</div>
