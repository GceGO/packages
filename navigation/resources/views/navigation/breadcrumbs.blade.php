{{-- gcego-navigation::navigation.breadcrumbs --}}
<nav aria-label="{{ __('gcego-navigation::messages.breadcrumbs') }}"{{ $nav->class('root', implode(' ', $presets::Breadcrumbs)) }}>
    <ol{{ $nav->class(\Gcego\Navigation\Navigation::BreadcrumbSlot, implode(' ', $presets::BreadcrumbList)) }}>
        @foreach ($crumbs as $crumb)
            <li class="{{ implode(' ', $presets::BreadcrumbItem) }}">
                @unless ($loop->first)
                    <x-gcego-icon name="navigation.breadcrumb-separator" size="none" :class="implode(' ', $presets::Separator)" />
                @endunless
                @if ($crumb->current)
                    <span aria-current="page" class="{{ implode(' ', $presets::BreadcrumbCurrent) }}">{{ $crumb->label }}</span>
                @elseif ($crumb->url !== null)
                    <a href="{{ $crumb->url }}" class="{{ implode(' ', $presets::BreadcrumbLink) }}">{{ $crumb->label }}</a>
                @else
                    <span class="truncate">{{ $crumb->label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
