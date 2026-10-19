{{-- gcego-panels::shells.topbar — navigation in the top bar (desktop) or a drawer (mobile), content full width. --}}
@php($presets = \Gcego\Panels\Presets\PanelPresets::class)
<div class="{{ $shell->classes(\Gcego\Chroma\Tailwind::Wrapper, $presets::Wrapper['topbar']) }}">
    <header class="{{ $shell->classes(\Gcego\Panels\Panel::TopbarSlot, $presets::Topbar) }}">
        <div class="{{ $shell->classes(\Gcego\Panels\Panel::DrawerSlot, $presets::Drawer) }}">
            {{ app(\Gcego\Navigation\NavigationManager::class)->render($shell->navigation, 'mobile') }}
        </div>
        @include('gcego-panels::partials.branding', ['shell' => $shell])
        <div class="{{ implode(' ', $presets::TopbarNavigation) }}">
            {{ app(\Gcego\Navigation\NavigationManager::class)->render($shell->navigation, 'topbar') }}
        </div>
        <div class="{{ implode(' ', $presets::TopbarEnd) }}">
            @if ($shell->hasSwitcher())
                @include('gcego-panels::partials.switcher', ['shell' => $shell, 'align' => 'end'])
            @endif
            {{ app(\Gcego\Panels\Extensions\PanelExtensions::class)->topbar($shell) }}
            @include('gcego-panels::partials.user-menu', ['shell' => $shell])
        </div>
    </header>
    <div class="{{ $shell->classes(\Gcego\Panels\Panel::MainSlot, $presets::Main) }}">
        @include('gcego-panels::partials.content', ['shell' => $shell, 'content' => $content])
    </div>
</div>
