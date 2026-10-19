{{-- gcego-pages::partials.breadcrumbs (gcego/navigation breadcrumbs and presets) --}}
@php($nav = \Gcego\Navigation\Presets\NavigationPresets::class)
<nav aria-label="{{ __('gcego-pages::messages.breadcrumbs') }}" class="{{ $class }}">
    <ol class="{{ implode(' ', $nav::BreadcrumbList) }}">
        @foreach ($crumbs as $crumb)
            <li class="{{ implode(' ', $nav::BreadcrumbItem) }}">
                @unless ($loop->first)
                    <x-gcego-icon name="navigation.breadcrumb-separator" size="none" :class="implode(' ', $nav::Separator)" />
                @endunless
                @if ($crumb->current)
                    <span aria-current="page" class="{{ implode(' ', $nav::BreadcrumbCurrent) }}">{{ $crumb->label }}</span>
                @elseif ($crumb->url !== null)
                    <a href="{{ $crumb->url }}" class="{{ implode(' ', $nav::BreadcrumbLink) }}">{{ $crumb->label }}</a>
                @else
                    <span class="truncate">{{ $crumb->label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
