{{-- gcego-panels::partials.branding — logo (light/dark) and name, linking to the panel home. URLs were validated
     when the panel was defined (root-relative or https); everything is escaped. --}}
@php($presets = \Gcego\Panels\Presets\PanelPresets::class)
@php($branding = $shell->branding)
@php($logo = $branding?->logo())
@php($tag = $shell->homeUrl === null ? 'span' : 'a')
<{{ $tag }} @if ($shell->homeUrl !== null) href="{{ $shell->homeUrl }}" @endif class="{{ $shell->classes(\Gcego\Panels\Panel::BrandingSlot, $presets::Branding) }}">
    @if ($logo === null)
        {{-- No logo: a monogram of the brand name, so the brand keeps a visual anchor. --}}
        <span aria-hidden="true" class="{{ implode(' ', $presets::Monogram) }}">{{ mb_strtoupper(mb_substr($shell->brandName(), 0, 1)) }}</span>
    @else
        <img src="{{ $logo }}" alt="" class="{{ implode(' ', [...$presets::Logo, ...($branding?->logoDark() !== null ? $presets::LogoLight : [])]) }}">
        @if ($branding?->logoDark() !== null)
            <img src="{{ $branding->logoDark() }}" alt="" class="{{ implode(' ', [...$presets::Logo, ...$presets::LogoDark]) }}">
        @endif
    @endif
    <span class="{{ implode(' ', $presets::BrandText) }}">
        <span class="{{ implode(' ', $presets::BrandName) }}">{{ $shell->brandName() }}</span>
        @if ($branding?->tagline() !== null)
            <span class="{{ implode(' ', $presets::Tagline) }}">{{ $branding->tagline() }}</span>
        @endif
    </span>
</{{ $tag }}>
