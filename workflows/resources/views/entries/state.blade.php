{{-- gcego-workflows::entries.state --}}
@if ($cell['text'] === null)
    <span class="text-gcego-text-muted">{{ __('gcego-workflows::messages.not_started') }}</span>
@else
    <span class="inline-flex min-w-0 flex-col">
        <span class="{{ app(\Gcego\Workflows\Support\WorkflowStyles::class)->tone($cell['color']) }}">{{ $cell['text'] }}<span class="sr-only">, {{ __('gcego-workflows::messages.statuses.'.$cell['status']) }}</span></span>
        @if ($cell['since'] !== null)
            <time datetime="{{ $cell['since'] }}" class="text-xs text-gcego-text-muted">{{ __('gcego-workflows::messages.since', ['date' => $cell['since_human']]) }}</time>
        @endif
    </span>
@endif
