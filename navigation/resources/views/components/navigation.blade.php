{{-- <x-gcego-navigation::navigation name="admin" display="topbar" /> — a registered navigation for the current user and page --}}
@props(['name', 'display' => null])
{{ app(\Gcego\Navigation\NavigationManager::class)->render((string) $name, is_string($display) ? $display : null) }}
