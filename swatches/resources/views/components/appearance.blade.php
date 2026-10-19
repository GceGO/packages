{{--
    <x-gcego-swatches::appearance />   Place in <head>, before stylesheets, to avoid a flash of the wrong mode.
    <x-gcego-swatches::appearance :nonce="Vite::cspNonce()" />   With a Content Security Policy.
    The tag is built by SwatchesManager::appearanceTag() so formatters cannot alter the script.
--}}
@props(['nonce' => null])
{{ app(\Gcego\Swatches\SwatchesManager::class)->appearanceTag($nonce) }}
