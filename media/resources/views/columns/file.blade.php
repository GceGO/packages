{{-- gcego-media::columns.file --}}
@if ($cell['count'] === 0)
    <span class="text-gcego-text-muted" aria-label="{{ __('gcego-media::messages.no_files') }}">—</span>
@else
    <span class="inline-flex min-w-0 flex-col">
        @if ($cell['url'] !== null)
            <a href="{{ $cell['url'] }}" class="truncate font-medium text-gcego-primary hover:underline focus-visible:outline-2 focus-visible:outline-gcego-ring rounded-gcego">{{ $cell['name'] }}</a>
        @else
            <span class="truncate">{{ $cell['name'] }}</span>
        @endif
        <span class="text-xs text-gcego-text-muted">{{ $cell['meta'] }}@if ($cell['count'] > 1) · {{ trans_choice('gcego-media::messages.more_files', $cell['count'] - 1, ['count' => $cell['count'] - 1]) }}@endif</span>
    </span>
@endif
