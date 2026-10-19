{{-- gcego-panels::shells.sidebar — a sidebar (desktop) or drawer (mobile) navigation beside the content.
     Also the "compact" layout (a narrower sidebar). --}}
@php($presets = \Gcego\Panels\Presets\PanelPresets::class)
@php($layout = $shell->state->layout === 'compact' ? 'compact' : 'sidebar')
<div class="{{ $shell->classes(\Gcego\Chroma\Tailwind::Wrapper, $presets::Wrapper[$layout]) }}"
    @if ($shell->state->collapsible) x-data="{{ $shell->collapseState() }}" @endif>
    <aside id="{{ $shell->id('sidebar') }}" aria-label="{{ __('gcego-panels::messages.sidebar', ['panel' => $shell->panel->title()]) }}" class="{{ $shell->classes(\Gcego\Panels\Panel::SidebarSlot, $presets::Sidebar[$layout]) }}"
        @if ($shell->state->collapsible) x-bind:class="collapsed && @js($shell->collapsedClasses())" @endif>
        <div class="{{ $shell->classes(\Gcego\Panels\Panel::SidebarHeaderSlot, $presets::SidebarHeader) }}">
            @include('gcego-panels::partials.branding', ['shell' => $shell])
        </div>
        <div class="{{ $shell->classes(\Gcego\Panels\Panel::SidebarBodySlot, $presets::SidebarBody) }}">
            {{ app(\Gcego\Navigation\NavigationManager::class)->render($shell->navigation) }}
        </div>
        <div class="{{ $shell->classes(\Gcego\Panels\Panel::SidebarFooterSlot, $presets::SidebarFooter) }}">
            @if ($shell->hasSwitcher())
                @include('gcego-panels::partials.switcher', ['shell' => $shell, 'align' => 'start'])
            @endif
            @include('gcego-panels::partials.profile', ['shell' => $shell])
        </div>
    </aside>
    @if ($shell->state->collapsible)
        {{ $shell->collapsePrelude() }}
    @endif
    <div class="{{ $shell->classes(\Gcego\Panels\Panel::MainSlot, $presets::Main) }}">
        <header class="{{ $shell->classes(\Gcego\Panels\Panel::TopbarSlot, $presets::Topbar) }}">
            <div class="{{ $shell->classes(\Gcego\Panels\Panel::DrawerSlot, $presets::Drawer) }}">
                {{ app(\Gcego\Navigation\NavigationManager::class)->render($shell->navigation, 'mobile') }}
            </div>
            @if ($shell->state->collapsible)
                <button type="button" x-on:click="toggle()" aria-controls="{{ $shell->id('sidebar') }}" aria-expanded="true" x-bind:aria-expanded="(! collapsed).toString()" class="{{ implode(' ', $presets::Toggle) }}">
                    {{ $shell->svg('navigation.menu') }}
                    <span class="sr-only">{{ __('gcego-panels::messages.toggle_sidebar') }}</span>
                </button>
            @endif
            <div class="lg:hidden">
                @include('gcego-panels::partials.branding', ['shell' => $shell])
            </div>
            <div class="{{ implode(' ', $presets::TopbarEnd) }}">
                @if ($shell->hasSwitcher())
                    <div class="lg:hidden">@include('gcego-panels::partials.switcher', ['shell' => $shell, 'align' => 'end'])</div>
                @endif
                {{ app(\Gcego\Panels\Extensions\PanelExtensions::class)->topbar($shell) }}
                <div class="{{ implode(' ', $presets::TopbarUserMenu) }}"@if ($shell->state->collapsible) x-bind:class="{ {{ \Illuminate\Support\Js::from(implode(' ', $presets::TopbarUserMenu)) }}: ! collapsed }"@endif>
                    @include('gcego-panels::partials.user-menu', ['shell' => $shell])
                </div>
            </div>
        </header>
        @include('gcego-panels::partials.content', ['shell' => $shell, 'content' => $content])
    </div>
</div>
