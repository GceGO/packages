{{-- gcego-layouts::layouts.layout --}}
<div id="{{ $layout->id() }}"{{ $layout->class('root', $flow) }}{{ $layout->key() }}>
    @if ($layout->title() !== null)
        <header{{ $layout->class('header', 'flex flex-col gap-1') }}>
            <h{{ $layout->level() }}{{ $layout->class('title', implode(' ', $presets::PageHeading)) }}>{{ $layout->title() }}</h{{ $layout->level() }}>
            @if ($layout->description() !== null)
                <p{{ $layout->class('description', implode(' ', $presets::Description)) }}>{{ $layout->description() }}</p>
            @endif
        </header>
    @endif
    {{ $layout->content() }}
</div>
