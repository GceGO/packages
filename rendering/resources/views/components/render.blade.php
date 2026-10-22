{{-- <x-gcego-rendering::render target="table:subjects" />

    Renders a registered definition statically after checking the RenderAuthorizer.
--}}
@props(['target', 'panel' => null, 'theme' => null])
{{ \Gcego\Rendering\Facades\Rendering::renderAuthorized($target, panel: $panel, theme: $theme) }}
