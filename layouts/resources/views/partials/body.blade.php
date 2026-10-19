{{-- The body (or empty text) and footer of a Section or Card. --}}
@php($footer = $layout->region(\Gcego\Layouts\Components\Footer::class))
<div{{ $layout->class('body', $body) }}>
    @if ($layout->items === [] && $component->emptyText() !== null)
        <p{{ $layout->class('empty', implode(' ', $presets::EmptyText)) }}>{{ __($component->emptyText()) }}</p>
    @else
        {{ $layout->content() }}
    @endif
</div>
@if ($footer !== null)
    <div{{ $footer->class('root', $layout->classes('footer', implode(' ', $presets::Footer))) }}{{ $footer->key() }}>{{ $footer->content() }}</div>
@endif
