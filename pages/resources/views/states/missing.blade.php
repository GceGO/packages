{{-- gcego-pages::states.missing: the record of an open page was deleted or moved out of reach --}}
<div data-gcego-page-host>
    <div role="status" class="{{ $class }}">
        <p class="{{ implode(' ', \Gcego\Pages\Presets\PagePresets::EmptyTitle) }}">{{ __('gcego-pages::messages.missing') }}</p>
        <p class="{{ implode(' ', \Gcego\Pages\Presets\PagePresets::EmptyText) }}">{{ __('gcego-pages::messages.missing_help') }}</p>
        @if ($url !== null)
            <a href="{{ $url }}" class="{{ app(\Gcego\Chroma\ChromaManager::class)->merge(app(\Gcego\Chroma\ChromaManager::class)->preset('control.button'), app(\Gcego\Chroma\ChromaManager::class)->preset('control.button.secondary')) }}">{{ __('gcego-pages::messages.back') }}</a>
        @endif
    </div>
</div>
