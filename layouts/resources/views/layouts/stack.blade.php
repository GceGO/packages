{{-- gcego-layouts::layouts.stack --}}
<{{ $element }} id="{{ $layout->id() }}"{{ $layout->class('root', $stack) }}{{ $layout->key() }}>{{ $layout->content() }}</{{ $element }}>
