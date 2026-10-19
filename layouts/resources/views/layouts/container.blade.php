{{-- gcego-layouts::layouts.container --}}
<{{ $element }} id="{{ $layout->id() }}"{{ $layout->class('root', $container) }}{{ $layout->key() }}>{{ $layout->content() }}</{{ $element }}>
