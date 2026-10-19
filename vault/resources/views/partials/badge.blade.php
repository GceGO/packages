{{-- gcego-vault::partials.badge — a status chip; the text always carries the meaning. --}}
<span class="{{ implode(' ', [...$p::Badge, ...($p::Tones[$tone ?? 'neutral'] ?? $p::Tones['neutral'])]) }}">{{ $text }}</span>
