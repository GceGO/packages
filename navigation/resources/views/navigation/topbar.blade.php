{{-- gcego-navigation::navigation.topbar — links and dropdowns in a row; it wraps instead of clipping dropdowns --}}
<nav id="{{ $nav->id() }}" aria-label="{{ $nav->label() }}"{{ $nav->class('root', implode(' ', $presets::Topbar)) }}>
    <ul role="list"{{ $nav->class(\Gcego\Navigation\Navigation::TopbarSlot, implode(' ', $presets::TopbarList)) }}>
        @foreach ($nav->navigation->nodes as $node)
            @if ($node->isLink())
                <li>@include('gcego-navigation::partials.link', ['node' => $node, 'base' => $presets::TopbarItem])</li>
            @elseif ($node->isContainer())
                @include('gcego-navigation::partials.dropdown', ['node' => $node])
            @endif
        @endforeach
    </ul>
</nav>
