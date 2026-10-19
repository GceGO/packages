<section class="{{ $styles('root') }}" @if ($heading) aria-labelledby="{{ $context->domId('heading') }}" @endif>
    @if ($heading || $description)
        <header class="{{ $styles('header') }}">
            @if ($icon)
                <span class="{{ implode(' ', $presets::SectionIcon) }}" aria-hidden="true"><x-gcego-icon :name="$icon" class="size-5" decorative /></span>
            @endif
            <div class="min-w-0">
                @if ($heading)
                    <h3 id="{{ $context->domId('heading') }}" class="{{ $styles('heading') }}">{{ $heading }}</h3>
                @endif
                @if ($description)
                    <p class="{{ $styles('description') }}">{{ $description }}</p>
                @endif
            </div>
        </header>
    @endif
    <div class="{{ $styles('body') }}">{{ $children }}</div>
</section>
