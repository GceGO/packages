{{-- gcego-workflows::livewire.approval — one approval decision or task of the signed-in user. --}}
@php($p = \Gcego\Workflows\Presets\WorkflowPresets::class)
<div>
    @if ($record)
        <section class="{{ $styles->classes('approval', $p::Card) }}" aria-labelledby="gcego-task-{{ $this->getId() }}">
            <header class="{{ implode(' ', $p::CardHeader) }}">
                <h3 id="gcego-task-{{ $this->getId() }}" class="{{ implode(' ', $p::CardTitle) }}">{{ $labels->task($record) }}</h3>
                <span class="text-xs text-gcego-text-muted">{{ __('gcego-workflows::messages.kinds.'.$record->kind) }}</span>
            </header>
            <div class="flex flex-col gap-3 px-4 py-3">
                <div role="status" aria-live="polite">
                    @if ($message)
                        <p class="text-sm text-gcego-text-muted">{{ $message }}</p>
                    @endif
                </div>
                @error('approval')
                    <p class="{{ implode(' ', $p::Error) }}" role="alert">{{ $message }}</p>
                @enderror

                <label class="{{ implode(' ', $p::Field) }}">
                    {{ __('gcego-workflows::messages.comment') }}
                    <textarea wire:model="comment" rows="2" maxlength="2000" class="{{ implode(' ', $p::Control) }}"></textarea>
                </label>

                <div class="{{ implode(' ', $p::Actions) }}" role="group" aria-label="{{ __('gcego-workflows::messages.decision') }}">
                    @if ($record->isApproval())
                        <button type="button" wire:click="decide('approve')" wire:loading.attr="disabled" class="{{ $styles->button('primary') }}">{{ __('gcego-workflows::messages.decisions.approve') }}</button>
                        <button type="button" wire:click="decide('changes')" wire:loading.attr="disabled" class="{{ $styles->button() }}">{{ __('gcego-workflows::messages.decisions.changes') }}</button>
                        <button type="button" wire:click="decide('reject')" wire:confirm="{{ __('gcego-workflows::messages.reject_confirm') }}" wire:loading.attr="disabled" class="{{ $styles->button('destructive') }}">{{ __('gcego-workflows::messages.decisions.reject') }}</button>
                        @if ($approval?->allowsAbstaining())
                            <button type="button" wire:click="decide('abstain')" wire:loading.attr="disabled" class="{{ $styles->button() }}">{{ __('gcego-workflows::messages.decisions.abstain') }}</button>
                        @endif
                    @else
                        <button type="button" wire:click="complete" wire:loading.attr="disabled" class="{{ $styles->button('primary') }}">{{ __('gcego-workflows::messages.complete') }}</button>
                    @endif
                </div>

                @if ($candidates !== [])
                    <form wire:submit="delegate" class="{{ implode(' ', $p::Toolbar) }}">
                        <label class="{{ implode(' ', $p::Field) }}">
                            {{ __('gcego-workflows::messages.delegate_to') }}
                            <select wire:model="delegateTo" class="{{ implode(' ', $p::Control) }}">
                                <option value="">{{ __('gcego-workflows::messages.choose') }}</option>
                                @foreach ($candidates as $person)
                                    <option value="{{ $person->getMorphClass().'#'.$person->getKey() }}">{{ \Gcego\Workflows\Support\WorkflowLabels::name($person) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="{{ $styles->button() }}">{{ __('gcego-workflows::messages.delegate') }}</button>
                    </form>
                @endif
            </div>
        </section>
    @endif
</div>
