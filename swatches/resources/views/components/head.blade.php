{{--
    <x-gcego-swatches::head />   Everything a page needs in <head>, in the right order:
    appearance controller, theme, tenant brand, accents. Pair it with
    <html {{ \Gcego\Swatches\Facades\Swatches::rootAttributes($theme) }}>.
--}}
@props(['theme' => null, 'brand' => null, 'nonce' => null])
@php($swatches = app(\Gcego\Swatches\SwatchesManager::class))
{{ $swatches->appearanceTag($nonce) }}
{{ $swatches->themeTag($theme, $nonce) }}
{{ $swatches->brandTag($brand, $theme, $nonce) }}
{{ $swatches->accentTag($theme, $nonce) }}
