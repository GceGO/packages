@if ($cell['src'] === null)
    <span class="inline-flex text-muted-foreground" aria-hidden="true">{{ $icons->svg('tables.image-placeholder', 'size-6') }}</span>
@else
    <img src="{{ $cell['src'] }}" alt="{{ $cell['alt'] }}" loading="lazy" decoding="async" width="40" height="40" class="{{ implode(' ', $cell['round'] ? $presets::RoundImage : $presets::Image) }} inline-block">
@endif
