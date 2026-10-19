{{--
    <x-gcego-chroma::appearance />   Kept for existing layouts; the same as <x-gcego-swatches::appearance />.
    Place in <head>, before stylesheets, to avoid a flash of the wrong mode.
--}}
@props(['nonce' => null])
{{ app(\Gcego\Swatches\SwatchesManager::class)->appearanceTag($nonce) }}
