{{-- gcego-pages::pages.page --}}
@php($presets = \Gcego\Pages\Presets\PagePresets::class)
@php($icons = app(\Gcego\Tables\Support\Icons::class))
<div data-gcego-page="{{ $page->page->identity }}" aria-labelledby="{{ $page->id('title') }}"{{ $page->class(\Gcego\Chroma\Tailwind::Wrapper, $presets::Wrapper, $page->widthClass()) }}>
    <header{{ $page->class(\Gcego\Chroma\Tailwind::Header, $presets::Header) }}>
        {{-- A lone crumb is the current page, which the title already names. --}}
        @if (count($page->breadcrumbs()) > 1)
            @include('gcego-pages::partials.breadcrumbs', ['crumbs' => $page->breadcrumbs(), 'class' => $page->classes(\Gcego\Pages\Page::BreadcrumbsSlot, \Gcego\Navigation\Presets\NavigationPresets::Breadcrumbs)])
        @endif
        <div class="{{ implode(' ', $presets::HeaderRow) }}">
            <div class="{{ implode(' ', $presets::HeaderText) }}">
                @if ($page->icon() !== null && $icons->has($page->icon()))
                    <span aria-hidden="true"{{ $page->class(\Gcego\Chroma\Tailwind::Icon, $presets::Icon) }}>{{ $icons->svg($page->icon(), implode(' ', $presets::IconSvg)) }}</span>
                @endif
                <div class="min-w-0">
                    <h{{ $level }} id="{{ $page->id('title') }}"{{ $page->class(\Gcego\Chroma\Tailwind::Title, $presets::Title) }}>{{ $page->title() }}</h{{ $level }}>
                    @if ($page->description() !== null)
                        <p{{ $page->class(\Gcego\Chroma\Tailwind::Description, $presets::Description) }}>{{ $page->description() }}</p>
                    @endif
                </div>
            </div>
            @if ($page->actions !== [])
                <div role="group" aria-label="{{ __('gcego-pages::messages.actions') }}"{{ $page->class(\Gcego\Chroma\Tailwind::Actions, $presets::Actions) }}>
                    @foreach ($page->actions as $action)
                        {{ $action }}
                    @endforeach
                </div>
            @endif
        </div>
    </header>
    <div{{ $page->class(\Gcego\Chroma\Tailwind::Body, $presets::Body) }}>
        @forelse ($page->body as $part)
            {{ $part }}
        @empty
            <div role="status"{{ $page->class(\Gcego\Pages\Page::EmptySlot, $presets::Empty) }}>
                <span aria-hidden="true">{{ $icons->svg('pages.empty', implode(' ', $presets::EmptyIcon)) }}</span>
                <p class="{{ implode(' ', $presets::EmptyTitle) }}">{{ $page->emptyText() }}</p>
            </div>
        @endforelse
    </div>
</div>
