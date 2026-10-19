{{-- gcego-vault::livewire.requests — one's own access requests. --}}
<div class="{{ implode(' ', $p::Screen) }}" data-vault-screen="requests">
    <x-gcego-vault::header :title="__('Access requests')" :lead="__('Ask for the access you need. Other people review it; you never approve your own request.')" :scope="$scopeLabel" />

    @include('gcego-vault::partials.feedback')

    <section class="{{ implode(' ', [...$p::Card, ...$p::CardBody]) }}" aria-labelledby="vault-new-request">
        <h2 id="vault-new-request" class="{{ implode(' ', $p::CardTitle) }}">{{ __('New request') }}</h2>
        <p class="{{ implode(' ', $p::Lead) }}">{{ trans_choice('It needs :count approval (:sensitive for sensitive access).|It needs :count approvals (:sensitive for sensitive access).', $approvals['normal'], ['count' => $approvals['normal'], 'sensitive' => $approvals['sensitive']]) }}</p>
        <form wire:submit="submit" class="mt-4 {{ implode(' ', $p::Grid) }}">
            <fieldset class="md:col-span-2">
                <legend class="{{ implode(' ', $p::Label) }}">{{ __('I need') }}</legend>
                <div class="flex gap-4 text-sm text-gcego-text">
                    <label class="inline-flex items-center gap-2"><input type="radio" wire:model.live="kind" value="role"> {{ __('A role') }}</label>
                    <label class="inline-flex items-center gap-2"><input type="radio" wire:model.live="kind" value="permission"> {{ __('One permission') }}</label>
                </div>
            </fieldset>
            @if ($kind === 'role')
                <div>
                    <label for="vault-req-role" class="{{ implode(' ', $p::Label) }}">{{ __('Role') }}</label>
                    <select id="vault-req-role" wire:model="role" class="{{ $s->input() }}"><option value="">{{ __('Choose a role') }}</option>@foreach ($roleOptions as $option)<option value="{{ $option->id }}">{{ $option->label }}</option>@endforeach</select>
                    @error('role')<p class="{{ implode(' ', $p::Error) }}">{{ $message }}</p>@enderror
                </div>
            @else
                <div>
                    <label for="vault-req-permission" class="{{ implode(' ', $p::Label) }}">{{ __('Permission') }}</label>
                    <input id="vault-req-permission" type="text" wire:model="requested" list="vault-req-permissions" maxlength="150" class="{{ $s->input('font-mono') }}">
                    <datalist id="vault-req-permissions">@foreach ($permissionOptions as $option)<option value="{{ $option->name }}">{{ $option->label }}</option>@endforeach</datalist>
                    @error('permission')<p class="{{ implode(' ', $p::Error) }}">{{ $message }}</p>@enderror
                </div>
            @endif
            <div>
                <label for="vault-req-until" class="{{ implode(' ', $p::Label) }}">{{ __('Needed until (optional)') }}</label>
                <input id="vault-req-until" type="date" wire:model="until" min="{{ now()->addDay()->toDateString() }}" @if ($maxDate) max="{{ $maxDate }}" @endif class="{{ $s->input() }}">
                @error('until')<p class="{{ implode(' ', $p::Error) }}">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                <label for="vault-req-reason" class="{{ implode(' ', $p::Label) }}">{{ __('Why do you need it?') }}</label>
                <textarea id="vault-req-reason" wire:model="reason" rows="3" minlength="10" maxlength="2000" required class="{{ $s->input() }}"></textarea>
                @error('reason')<p class="{{ implode(' ', $p::Error) }}">{{ $message }}</p>@enderror
            </div>
            <div class="flex justify-end md:col-span-2"><button type="submit" class="{{ $s->button('primary') }}">{{ __('Send request') }}</button></div>
        </form>
    </section>

    <section class="{{ implode(' ', $p::Card) }}" aria-labelledby="vault-my-requests">
        <div class="{{ implode(' ', $p::Toolbar) }}"><h2 id="vault-my-requests" class="{{ implode(' ', $p::CardTitle) }}">{{ __('My requests') }}</h2></div>
        <ul class="divide-y divide-gcego-border">
            @forelse ($requests as $request)
                <li class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between" wire:key="request-{{ $request->id }}">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gcego-text">{{ $request->role?->label ?? $request->permission?->name }}
                            @include('gcego-vault::partials.badge', ['tone' => $request->status->tone(), 'text' => $request->status->label()])
                            @if ($request->is_sensitive)@include('gcego-vault::partials.badge', ['tone' => 'warning', 'text' => __('Sensitive')])@endif
                        </p>
                        <p class="text-xs text-gcego-text-muted">{{ __('Asked :when', ['when' => $request->created_at?->diffForHumans()]) }} · {{ __(':done of :needed approvals', ['done' => $request->approvals->where('decision', 'approve')->count(), 'needed' => $request->approvals_required]) }}@if ($request->decision_reason) · {{ $request->decision_reason }}@endif</p>
                    </div>
                    @if ($request->isPending() && $request->requester_type === $me->type && $request->requester_id === $me->id)
                        <button type="button" wire:click="cancelRequest({{ $request->id }})" class="{{ $s->button('secondary', 'text-xs') }}">{{ __('Withdraw') }}</button>
                    @endif
                </li>
            @empty
                <li class="{{ implode(' ', $p::Empty) }}">{{ __('You have not asked for anything yet.') }}</li>
            @endforelse
        </ul>
    </section>
</div>
