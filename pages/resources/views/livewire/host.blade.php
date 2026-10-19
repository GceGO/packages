{{-- gcego-pages::livewire.host --}}
<div data-gcego-page-host>
    <div wire:loading.delay.long role="status" class="{{ $loading }}"><span class="sr-only">{{ __('gcego-pages::messages.loading') }}</span></div>
    {{ $content }}
</div>
