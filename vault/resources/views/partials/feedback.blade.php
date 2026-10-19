{{-- gcego-vault::partials.feedback — the result of the last change (announced to assistive technology). --}}
<div aria-live="polite" class="min-h-0">
    @if ($feedback !== null)
        <div role="{{ $feedback['tone'] === 'error' ? 'alert' : 'status' }}" class="{{ implode(' ', [...$p::Notice, ...($p::NoticeTones[$feedback['tone']] ?? $p::NoticeTones['info'])]) }} flex items-start justify-between gap-3" data-vault-feedback="{{ $feedback['tone'] }}">
            <p>{{ $feedback['message'] }}</p>
            <button type="button" wire:click="dismissFeedback" class="text-gcego-text-muted hover:text-gcego-text focus-visible:outline-2 focus-visible:outline-gcego-ring rounded-sm" aria-label="{{ __('Dismiss') }}">&times;</button>
        </div>
    @endif
</div>
