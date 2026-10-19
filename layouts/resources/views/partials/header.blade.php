{{-- Title, icon, description and (unless $withActions is false) header actions of a Section or Card.
     Inside a <summary> (collapsible sections) it uses phrasing elements only. --}}
@php($actions = ($withActions ?? true) ? $layout->region(\Gcego\Layouts\Components\Actions::class) : null)
@php($icon = $layout->icon())
@php($inSummary = ! ($withActions ?? true))
@php($box = $inSummary ? 'span' : 'div')
@php($text = $inSummary ? 'span' : 'p')
<{{ $box }}{{ $layout->class('header', implode(' ', $presets::Header)) }}>
    <{{ $box }} class="{{ implode(' ', $presets::HeaderText) }}">
        @if ($icon !== null)
            <span aria-hidden="true"{{ $layout->class('icon', implode(' ', $presets::IconBadge)) }}>{{ $icon }}</span>
        @endif
        <{{ $box }} class="block min-w-0">
            <h{{ $layout->level() }} id="{{ $layout->id('title') }}"{{ $layout->class('title', implode(' ', $presets::Heading)) }}>{{ $layout->title() }}</h{{ $layout->level() }}>
            @if ($layout->description() !== null)
                <{{ $text }}{{ $layout->class('description', implode(' ', $presets::Description), 'block') }}>{{ $layout->description() }}</{{ $text }}>
            @endif
        </{{ $box }}>
    </{{ $box }}>
    @if ($actions !== null)
        <div{{ $actions->class('root', $layout->classes('actions', implode(' ', $presets::Actions))) }}{{ $actions->key() }}>{{ $actions->content() }}</div>
    @endif
</{{ $box }}>
