<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Admin Studio
|--------------------------------------------------------------------------
|
| Studio assembles the GCEGO packages into one product. It owns no engine:
| panels, modules, pages, navigation, styling and security keep their own
| configuration; the values below only feed their documented settings.
| Nothing here can make a panel public or skip authorization.
|
*/

return [

    'enabled' => true,

    // Discovery of each kind of definition (false removes Studio-managed discovery for it).
    'discovery' => [
        'panels' => true,
        'modules' => true,
        'pages' => true,
    ],

    // Framework-wide appearance defaults; null keeps the package default (gcego-panels.appearance).
    'appearance' => [
        'default_layout' => null,   // sidebar, topbar or compact
        'default_theme' => null,    // a gcego/chroma theme name
    ],

    'assets' => [
        // Vite entries added to every panel page (null keeps gcego-panels.assets.vite).
        'vite' => null,
        // Files the asset checks read (never modified).
        'css' => resource_path('css/app.css'),
        'js' => resource_path('js/app.js'),
        'manifest' => public_path('build/manifest.json'),
    ],

    // What the health checks require of panels. These only make checks stricter:
    // panels stay authenticated and deny-by-default whatever is set here.
    'security' => [
        'require_authentication' => true,
        'require_authorization' => true,
    ],

    'diagnostics' => [
        // Show file paths and configuration details in production diagnostics.
        'production_details' => false,
    ],

    // Trusted StudioExtension classes (also registrable with Studio::extend()).
    'extensions' => [],

    'install' => [
        // The starter panel gcego:install creates.
        'panel' => 'admin',
        'ability' => 'access-admin',
    ],

];
