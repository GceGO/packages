{{--
    <x-gcego-swatches::theme />                Custom-property overrides of the configured theme (nothing for built-in themes).
    <x-gcego-swatches::theme name="campus" />  A specific theme. Put the matching attributes on <html>:
                                                <html {{ \Gcego\Swatches\Facades\Swatches::rootAttributes('campus') }}>
--}}
@props(['name' => null, 'nonce' => null])
{{ app(\Gcego\Swatches\SwatchesManager::class)->themeTag($name, $nonce) }}
