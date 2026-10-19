@if ($cell['pairs'] === [] || $cell['text'] === null)
    @include('gcego-infolists::entries.placeholder')
@else
    <dl class="grid grid-cols-1 gap-x-4 gap-y-1.5 rounded-gcego border border-gcego-border px-3 py-2 sm:grid-cols-[max-content_minmax(0,1fr)]">
        @foreach ($cell['pairs'] as $pair)
            <dt class="text-xs font-medium text-gcego-text-muted">{{ $pair['label'] }}</dt>
            <dd class="min-w-0 break-words text-sm">{{ $pair['text'] ?? $placeholder }}</dd>
        @endforeach
    </dl>
@endif
