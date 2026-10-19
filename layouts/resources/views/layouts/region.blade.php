{{-- gcego-layouts::layouts.region — Main, Aside, Tab or AccordionItem outside their parent (Layout validation reports this) --}}
<{{ $element }} id="{{ $layout->id() }}"@if ($layout->title() !== null && $element !== 'div') aria-label="{{ $layout->title() }}"@endif{{ $layout->class('root', $region) }}{{ $layout->key() }}>{{ $layout->content() }}</{{ $element }}>
