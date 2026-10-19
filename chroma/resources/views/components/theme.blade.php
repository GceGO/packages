{{--
    <x-gcego-chroma::theme />                The configured theme's runtime overrides: colors (gcego/swatches) and
                                              non-color tokens; nothing when the static stylesheets cover it.
    <x-gcego-chroma::theme name="campus" />  A specific theme. Put the matching data-gcego-theme on <html>:
                                              <html {{ app(\Gcego\Chroma\ChromaManager::class)->rootAttributes('campus') }}>
--}}
@props(['name' => null, 'nonce' => null])
{{ app(\Gcego\Chroma\ChromaManager::class)->themeTag($name, $nonce) }}
