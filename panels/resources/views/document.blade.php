{{-- gcego-panels::document — the gcego/pages layout while gcego/panels is installed: the panel shell on
     panel routes, gcego/pages' own layout elsewhere. --}}
@php($shell = app(\Gcego\Panels\Shells\PanelShellFactory::class)->current($title))
@if ($shell === null)
    @include('gcego-pages::layouts.app')
@else
    @include('gcego-panels::shells.document', ['shell' => $shell, 'content' => $content])
@endif
