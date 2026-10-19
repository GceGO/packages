{{--
    <x-gcego-swatches::accents />   Styles for the main colors users may pick (GcegoAppearance.setAccent()).
--}}
@props(['theme' => null, 'nonce' => null])
{{ app(\Gcego\Swatches\SwatchesManager::class)->accentTag($theme, $nonce) }}
