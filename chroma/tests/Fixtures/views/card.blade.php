<article aria-labelledby="{{ $context->domId('title') }}"{{ $styles->attribute('root') }}>
    <header{{ $styles->attribute('header') }}>
        <h2 id="{{ $context->domId('title') }}"{{ $styles->attribute('title') }}>{{ $component->get('label') }}</h2>
    </header>
    <div{{ $styles->attribute('body') }}>{{ $children }}</div>
</article>
