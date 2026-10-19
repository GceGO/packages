<button type="button" data-copy="{{ $copyValue }}" x-on:click="navigator.clipboard?.writeText($el.dataset.copy); copied = true; setTimeout(() => copied = false, 1500)" class="{{ implode(' ', $presets::CopyButton) }}" aria-label="{{ __('gcego-infolists::messages.copy', ['label' => __($entry->label())]) }}">
    {{ $icons->svg('infolists.copy', 'size-3.5') }}
</button>
<span x-show="copied" x-cloak role="status" class="text-xs text-gcego-success">{{ __('gcego-infolists::messages.copied') }}</span>
