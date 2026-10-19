{{-- gcego-pages::components.content --}}
<div class="{{ trim($preset.' '.$styles(\Gcego\Chroma\Tailwind::Wrapper)) }}">
    @foreach ($paragraphs as $paragraph)
        <p>{!! nl2br(e($paragraph), false) !!}</p>
    @endforeach
</div>
