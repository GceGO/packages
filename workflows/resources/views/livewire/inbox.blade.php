{{-- gcego-workflows::livewire.inbox — the signed-in user's approvals and tasks. --}}
@php($p = \Gcego\Workflows\Presets\WorkflowPresets::class)
@php($items = array_values(array_filter($page->items())))
<div class="{{ $styles->classes('inbox', $p::Page) }}">
    <h1 class="{{ implode(' ', $p::Title) }}">{{ __('gcego-workflows::messages.inbox') }}</h1>

    <form class="{{ implode(' ', $p::Toolbar) }}" wire:submit.prevent role="search" aria-label="{{ __('gcego-workflows::messages.filters') }}">
        <label class="{{ implode(' ', $p::Field) }}">
            {{ __('gcego-workflows::messages.show') }}
            <select wire:model.live="filter" class="{{ implode(' ', $p::Control) }}">
                @foreach ($filters as $option)
                    <option value="{{ $option }}">{{ __('gcego-workflows::messages.inbox_filters.'.$option) }}</option>
                @endforeach
            </select>
        </label>
    </form>

    <div role="status" aria-live="polite">
        @if ($message)
            <p class="text-sm text-gcego-text-muted">{{ $message }}</p>
        @endif
    </div>
    @error('inbox')
        <p class="{{ implode(' ', $p::Error) }}" role="alert">{{ $message }}</p>
    @enderror

    <div class="{{ $styles->classes('card', $p::Card) }}" wire:loading.class="opacity-60">
        @if ($items === [])
            <div class="{{ $styles->classes('empty', $p::Empty) }}">
                <p class="{{ implode(' ', $p::EmptyText) }}">{{ __('gcego-workflows::messages.inbox_empty') }}</p>
            </div>
        @else
            <ul role="list" class="{{ implode(' ', $p::List) }}">
                @foreach ($items as $task)
                    <li class="{{ $styles->classes('item', $p::Item) }}" wire:key="task-{{ $task->ulid }}">
                        <div class="{{ implode(' ', $p::ItemText) }}">
                            <p class="{{ implode(' ', $p::ItemTitle) }}">
                                @if ($link)
                                    <a href="{{ str_replace(':instance', $task->instance->ulid, $link) }}" class="{{ implode(' ', $p::Link) }}">{{ $labels->task($task) }}</a>
                                @else
                                    {{ $labels->task($task) }}
                                @endif
                            </p>
                            <p class="{{ implode(' ', $p::ItemBody) }}">{{ $labels->title($task->instance) }}</p>
                            <p class="{{ implode(' ', $p::ItemMeta) }}">
                                <span class="{{ $styles->tone($labels->color($task->instance->workflow, $task->instance->state)) }}">{{ $labels->state($task->instance->workflow, $task->instance->state) }}</span>
                                <span>{{ __('gcego-workflows::messages.kinds.'.$task->kind) }}</span>
                                @if ($task->priority !== 'normal')
                                    <span class="{{ $styles->tone($task->priority === 'low' ? 'neutral' : 'warning') }}">{{ __('gcego-workflows::messages.priorities.'.$task->priority) }}</span>
                                @endif
                                @if ($task->overdue_at)
                                    <span class="{{ $styles->tone('danger') }}">{{ __('gcego-workflows::messages.overdue') }}</span>
                                @elseif ($task->due_at)
                                    <span>{{ __('gcego-workflows::messages.due', ['date' => $task->due_at->diffForHumans()]) }}</span>
                                @endif
                                @if ($task->pool)
                                    <span>{{ __('gcego-workflows::messages.pool') }}</span>
                                @endif
                            </p>
                        </div>
                        @if ($task->pool && ! $task->isApproval())
                            <button type="button" wire:click="claim('{{ $task->ulid }}')" class="{{ $styles->button() }}">{{ __('gcego-workflows::messages.claim') }}</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($page->hasPages())
        <nav aria-label="{{ __('gcego-workflows::messages.pagination') }}" class="flex items-center justify-between gap-3 text-sm">
            <button type="button" wire:click="previousPage" @disabled($page->onFirstPage()) class="{{ implode(' ', $p::Link) }} disabled:opacity-50">{{ __('gcego-workflows::messages.previous') }}</button>
            <span class="text-gcego-text-muted">{{ __('gcego-workflows::messages.page', ['page' => $page->currentPage(), 'pages' => $page->lastPage()]) }}</span>
            <button type="button" wire:click="nextPage" @disabled(! $page->hasMorePages()) class="{{ implode(' ', $p::Link) }} disabled:opacity-50">{{ __('gcego-workflows::messages.next') }}</button>
        </nav>
    @endif
</div>
