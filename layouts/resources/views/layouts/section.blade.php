{{-- gcego-layouts::layouts.section --}}
@if ($collapsible)
    <details id="{{ $layout->id() }}"{{ $layout->class('root', $surface, 'group') }}@if ($open) open @endif @if ($layout->live()) wire:ignore.self @endif{{ $layout->key() }}>
        <summary{{ $layout->class('summary', implode(' ', $presets::Summary)) }}>
            @include('gcego-layouts::partials.header', ['withActions' => false])
            @include('gcego-layouts::partials.chevron')
        </summary>
        @if (($actions = $layout->region(\Gcego\Layouts\Components\Actions::class)) !== null)
            <div{{ $actions->class('root', $layout->classes('actions', implode(' ', $presets::Actions))) }}{{ $actions->key() }}>{{ $actions->content() }}</div>
        @endif
        @include('gcego-layouts::partials.body')
    </details>
@elseif ($layout->title() !== null)
    <section id="{{ $layout->id() }}" aria-labelledby="{{ $layout->id('title') }}"{{ $layout->class('root', $surface) }}{{ $layout->key() }}>
        @include('gcego-layouts::partials.header')
        @include('gcego-layouts::partials.body')
    </section>
@else
    <div id="{{ $layout->id() }}"{{ $layout->class('root', $surface) }}{{ $layout->key() }}>
        @if (($actions = $layout->region(\Gcego\Layouts\Components\Actions::class)) !== null)
            <div{{ $actions->class('root', $layout->classes('actions', implode(' ', $presets::Actions), 'justify-end')) }}{{ $actions->key() }}>{{ $actions->content() }}</div>
        @endif
        @include('gcego-layouts::partials.body')
    </div>
@endif
