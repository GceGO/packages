@if ($cell['src'] === null)
    <span class="inline-flex text-gcego-text-muted" role="img" aria-label="{{ __('gcego-infolists::messages.no_image') }}">{{ $icons->svg('infolists.image-placeholder', 'size-8') }}</span>
@else
    <img src="{{ $cell['src'] }}" alt="{{ $cell['alt'] }}" loading="lazy" decoding="async" class="{{ $cell['size'] }} max-w-full bg-gcego-surface-muted object-cover {{ $cell['round'] ? 'rounded-full' : 'rounded-gcego' }}">
@endif
