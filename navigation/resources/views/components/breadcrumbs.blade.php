{{-- <x-gcego-navigation::breadcrumbs navigation="admin" /> --}}
@props(['navigation'])
{{ app(\Gcego\Navigation\NavigationManager::class)->render((string) $navigation, 'breadcrumbs') }}
