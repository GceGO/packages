{{-- gcego-workflows::columns.state --}}
@if ($cell['text'] === null)
    <span class="text-gcego-text-muted" aria-label="{{ __('gcego-workflows::messages.not_started') }}">—</span>
@else
    <span class="{{ app(\Gcego\Workflows\Support\WorkflowStyles::class)->tone($cell['color']) }}">{{ $cell['text'] }}<span class="sr-only">, {{ __('gcego-workflows::messages.statuses.'.$cell['status']) }}</span></span>
@endif
