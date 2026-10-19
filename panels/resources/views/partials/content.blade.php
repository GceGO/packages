{{-- gcego-panels::partials.content — the page content (gcego/pages' PageHost) and the footer. --}}
@php($presets = \Gcego\Panels\Presets\PanelPresets::class)
<main id="{{ $shell->id('content') }}" tabindex="-1" class="{{ $shell->classes(\Gcego\Chroma\Tailwind::Content, $presets::Content) }}">
    {{ $content }}
</main>
@if ($shell->panel->get(\Gcego\Panels\Panel::Description) !== null || $shell->tenantName !== null)
    <footer class="{{ $shell->classes(\Gcego\Chroma\Tailwind::Footer, $presets::Footer) }}">
        {{ $shell->panel->title() }}@if ($shell->tenantName !== null) · {{ $shell->tenantName }}@endif
    </footer>
@endif
