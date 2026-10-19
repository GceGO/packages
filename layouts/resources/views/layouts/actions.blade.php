{{-- gcego-layouts::layouts.actions — Actions outside a Section or Card header --}}
<div id="{{ $layout->id() }}"{{ $layout->class('root', $actions, $component->gapClasses(2), $component->alignClasses()) }}{{ $layout->key() }}>{{ $layout->content() }}</div>
