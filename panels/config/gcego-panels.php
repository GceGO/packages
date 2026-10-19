<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Panels
|--------------------------------------------------------------------------
|
| Panels are discovered by gcego/registry from app/GCEGo/Panels (kind
| "panel", every PHP file; see gcego-registry.discovery.roots). Each panel
| gets its own route prefix, route names, middleware, access rules,
| navigation and shell. Panels are never open by default: each declares who
| may enter (gcego/security requirements or an access policy).
|
*/

return [

    'discovery' => [
        // Folders whose PHP files return panels (added to gcego-registry.discovery.roots when missing).
        'paths' => [app_path('GCEGo/Panels')],
    ],

    'routing' => [
        // Route names: "<name_prefix><panel>.<page route>" ("gcego.admin.academics.subjects.index").
        'name_prefix' => 'gcego.',
        // Middleware every panel route starts with (then auth, verification, tenancy, panel access, the panel's own).
        'middleware' => ['web'],
        // Panels register the routes of their pages; gcego/pages then registers none of its own.
        'register_page_routes' => true,
    ],

    'security' => [
        // The guard of panels that do not name one (null: the application default).
        'guard' => null,
    ],

    'appearance' => [
        // sidebar, topbar or compact.
        'default_layout' => 'sidebar',
        // A gcego/chroma theme name (null: the styling default).
        'default_theme' => null,
    ],

    'navigation' => [
        // Let users collapse the desktop sidebar (remembered in their browser).
        'collapsible_sidebar' => true,
    ],

    // Classes implementing Gcego\Panels\Contracts\PanelExtension (packages usually register themselves).
    'extensions' => [],

    // Extra user-menu links: label => route name (shown when the route exists).
    'user_menu' => [],

    'assets' => [
        // Vite entries of panel pages (null: gcego-pages.rendering.vite).
        'vite' => null,
    ],

];
