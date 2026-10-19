{{-- gcego-layouts::layouts.card --}}
@if ($layout->title() !== null)
    <section id="{{ $layout->id() }}" aria-labelledby="{{ $layout->id('title') }}"{{ $layout->class('root', $card) }}{{ $layout->key() }}>
        @include('gcego-layouts::partials.header')
        @include('gcego-layouts::partials.body')
    </section>
@else
    <div id="{{ $layout->id() }}"{{ $layout->class('root', $card) }}{{ $layout->key() }}>
        @if (($actions = $layout->region(\Gcego\Layouts\Components\Actions::class)) !== null)
            <div{{ $actions->class('root', $layout->classes('actions', implode(' ', $presets::Actions), 'justify-end')) }}{{ $actions->key() }}>{{ $actions->content() }}</div>
        @endif
        @include('gcego-layouts::partials.body')
    </div>
@endif
