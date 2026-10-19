{{-- gcego-layouts::layouts.scroll-area — focusable so keyboard users can scroll it --}}
<div id="{{ $layout->id() }}" role="region" aria-label="{{ $layout->title() ?? __('gcego-layouts::messages.scrollable') }}" tabindex="0"{{ $layout->class('root', $scroll) }}{{ $layout->key() }}>{{ $layout->content() }}</div>
