<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Pages
|--------------------------------------------------------------------------
|
| Routing, authorization defaults and presentation of gcego/pages.
| Publish with "php artisan vendor:publish --tag=gcego-pages-config".
|
*/

return [

    'routing' => [
        // Register a route for every registered page when the application
        // boots (skipped when routes are cached). Turn it off when another
        // package (gcego/panels) registers page routes with its own prefix
        // through Gcego\Pages\Routing\PageRouteRegistrar.
        'enabled' => true,

        // URL prefix and route-name prefix of every page route:
        // page "subjects.create" answers GET /admin/subjects/create as "gcego.subjects.create".
        'prefix' => 'admin',
        'name_prefix' => 'gcego.',

        // Middleware of every page route ("web" for sessions and CSRF,
        // "auth" to send guests to the login page). Add "gcego.tenant" for
        // tenant-aware pages. Pages add their own with Page::Middleware.
        'middleware' => ['web', 'auth'],

        // Restrict page routes to one domain (null: any).
        'domain' => null,
    ],

    'authorization' => [
        // What a page with no requirement (no Permission, Ability,
        // Authenticated, ... on the page) allows:
        //   "deny"          - nobody (gcego/security's deny-by-default)
        //   "authenticated" - any signed-in user
        //   "public"        - everyone, guests included
        // Record pages always authorize the record too.
        'default' => 'deny',
    ],

    'pages' => [
        // Content width of pages without Page::Width: narrow, standard, wide or full.
        'default_width' => 'standard',

        // Route constraint of {record} for record pages without Page::RecordKey:
        // id, uuid, ulid, slug or key (letters, digits, "-" and "_").
        'record_key' => 'key',
    ],

    'breadcrumbs' => [
        'enabled' => true,

        // A page name that starts every trail (for example "dashboard"); shown only to users who may open it.
        'home' => null,

        // A navigation name ("admin"): start trails from that navigation's
        // active trail instead of the page hierarchy.
        'navigation' => null,
    ],

    'navigation' => [
        // Contribute pages that declare Page::Navigation to the target
        // navigation automatically. Pages are never added unless they opt in.
        'auto_register' => false,

        // The navigation pages are contributed to.
        'target' => 'admin',
    ],

    'rendering' => [
        // The Blade layout page routes render in (the future gcego/panels shell replaces it).
        'layout' => 'gcego-pages::layouts.app',

        // Vite entry points the default layout loads (your Tailwind CSS that imports gcego.css).
        'vite' => [],

        // A navigation rendered as a sidebar by the default layout (null: none).
        'navigation' => null,

        // Redirect between pages with wire:navigate (SPA-style) after actions.
        'wire_navigate' => false,
    ],

    'lifecycle' => [
        // Trusted Gcego\Pages\Contracts\PageLifecycleHook classes, called at every stage.
        'hooks' => [],
    ],

    'diagnostics' => [
        // The inspector warns above these sizes.
        'max_components' => 300,
        'max_livewire_hosts' => 8,
    ],

];
