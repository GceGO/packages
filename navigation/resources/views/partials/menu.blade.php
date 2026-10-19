{{-- Inside a dropdown: links, headings and dividers; nested groups become a heading and their links. --}}
@foreach ($nodes as $node)
    @if ($node->isLink())
        <li>@include('gcego-navigation::partials.link', ['node' => $node, 'base' => $presets::Item])</li>
    @elseif ($node->type === 'divider')
        <li aria-hidden="true"{{ $nav->part($node, \Gcego\Navigation\Navigation::DividerSlot, implode(' ', $presets::Divider)) }}></li>
    @elseif ($node->type === 'heading')
        <li{{ $nav->part($node, \Gcego\Navigation\Navigation::HeadingSlot, implode(' ', $presets::Heading)) }}>{{ $node->label }}</li>
    @else
        <li id="{{ $nav->nodeId($node, 'label') }}"{{ $nav->part($node, \Gcego\Navigation\Navigation::HeadingSlot, implode(' ', $presets::Heading)) }}>{{ $node->label }}</li>
        @include('gcego-navigation::partials.menu', ['nodes' => $node->children])
    @endif
@endforeach
