{{-- gcego-workflows::livewire.panel — state, transitions, work and management of one instance. --}}
@php($p = \Gcego\Workflows\Presets\WorkflowPresets::class)
<section class="{{ $styles->classes('panel', $p::Card) }}" aria-labelledby="gcego-workflow-{{ $this->getId() }}">
    <header class="{{ implode(' ', $p::CardHeader) }}">
        <h2 id="gcego-workflow-{{ $this->getId() }}" class="{{ implode(' ', $p::CardTitle) }}">{{ $record ? $labels->title($record) : ($definition?->title() ?? __('gcego-workflows::messages.workflow')) }}</h2>
        @if ($record)
            <span class="{{ $styles->tone($record->isRunning() ? $labels->color($record->workflow, $record->state) : ($record->status->value === 'cancelled' ? 'neutral' : 'success')) }}">
                {{ $labels->state($record->workflow, $record->state) }}<span class="sr-only">, {{ __('gcego-workflows::messages.statuses.'.$record->status->value) }}</span>
            </span>
        @endif
    </header>

    <div class="flex flex-col gap-3 px-4 py-3">
        <div role="status" aria-live="polite">
            @if ($message)
                <p class="text-sm text-gcego-text-muted">{{ $message }}</p>
            @endif
        </div>
        @error('workflow')
            <p class="{{ implode(' ', $p::Error) }}" role="alert">{{ $message }}</p>
        @enderror

        @if ($record === null && ! $startable)
            <p class="{{ implode(' ', $p::EmptyText) }}">{{ __('gcego-workflows::messages.not_started') }}</p>
        @endif

        @if ($record && ! $record->isRunning())
            <p class="text-sm text-gcego-text-muted">{{ __('gcego-workflows::messages.statuses.'.$record->status->value) }}</p>
        @elseif ($record && $record->status->value === 'suspended')
            <p class="text-sm text-gcego-text-muted">{{ __('gcego-workflows::messages.suspended') }}</p>
        @endif

        @if ($startable)
            <div class="{{ implode(' ', $p::Actions) }}">
                <button type="button" wire:click="start" class="{{ $styles->button('primary') }}">{{ __('gcego-workflows::messages.start') }}</button>
            </div>
        @endif

        @if ($available !== [] || $manageable)
            <label class="{{ implode(' ', $p::Field) }}">
                {{ __('gcego-workflows::messages.comment') }}
                <textarea wire:model="comment" rows="2" maxlength="2000" class="{{ implode(' ', $p::Control) }}"></textarea>
            </label>
        @endif

        @if ($available !== [])
            <div class="{{ $styles->classes('actions', $p::Actions) }}" role="group" aria-label="{{ __('gcego-workflows::messages.transitions') }}">
                @foreach ($available as $option)
                    <div class="flex flex-col gap-1">
                        <button type="button"
                            wire:click="runTransition('{{ $option->transition->nodeName() }}')"
                            @if ($option->transition->needsConfirmation()) wire:confirm="{{ __('gcego-workflows::messages.confirm', ['transition' => $option->transition->label()]) }}" @endif
                            @disabled(! $option->enabled())
                            @if (! $option->enabled()) aria-describedby="gcego-wf-reason-{{ $this->getId() }}-{{ $option->transition->nodeName() }}" @endif
                            wire:loading.attr="disabled"
                            class="{{ $styles->button($loop->first ? 'primary' : 'secondary') }} disabled:opacity-50">
                            {{ $option->transition->label() }}@if ($option->transition->comment() === 'required')<span class="sr-only"> ({{ __('gcego-workflows::messages.comment_required') }})</span>@endif
                        </button>
                        @if (! $option->enabled())
                            <span id="gcego-wf-reason-{{ $this->getId() }}-{{ $option->transition->nodeName() }}" class="text-xs text-gcego-text-muted">{{ $option->reason }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @foreach ($tasks as $task)
            <livewire:gcego-workflow-approval :task="$task->ulid" :key="'approval-'.$task->ulid.'-'.$task->status->value" />
        @endforeach

        @if ($manageable)
            <div class="{{ implode(' ', $p::Actions) }}" role="group" aria-label="{{ __('gcego-workflows::messages.manage') }}">
                @if ($record->status->value === 'active')
                    <button type="button" wire:click="manage('suspend')" class="{{ $styles->button() }}">{{ __('gcego-workflows::messages.commands.suspend') }}</button>
                @else
                    <button type="button" wire:click="manage('resume')" class="{{ $styles->button() }}">{{ __('gcego-workflows::messages.commands.resume') }}</button>
                @endif
                <button type="button" wire:click="manage('cancel')" wire:confirm="{{ __('gcego-workflows::messages.cancel_confirm') }}" class="{{ $styles->button('destructive') }}">{{ __('gcego-workflows::messages.commands.cancel') }}</button>
            </div>
        @endif
    </div>
</section>
