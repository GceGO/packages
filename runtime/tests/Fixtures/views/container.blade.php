<div data-path="{{ $context->path() }}"{{ $styles->attribute('root') }}>
    <ul>
        @foreach ($children as $child)
            <li>{{ $child }}</li>
        @endforeach
    </ul>
</div>
