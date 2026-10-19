<?php

declare(strict_types=1);

return [

    // Who sees nodes that have no requirement of their own or above them:
    //   "authenticated" - signed-in users (default), "public" - everyone,
    //   "deny" - nobody (every node needs a requirement, as gcego/security does by default).
    // Hiding a link is never access control: routes stay protected on their own.
    'visibility' => [
        'default' => 'authenticated',
    ],

    'sidebar' => [
        // Show the compact (icons only) toggle, and whether sidebars start compact.
        'compact_toggle' => true,
        'default_compact' => false,
        // A search field above the sidebar's links that filters them as the user types ("/" focuses it).
        'search' => true,
    ],

    'groups' => [
        // Whether group headers toggle their group (Group::Collapsible overrides).
        'collapsible' => true,
        // How deeply groups may nest.
        'max_depth' => 3,
    ],

    'active' => [
        // Whether an item's query string must match the current one to be active.
        'match_query_string' => false,
    ],

    'breadcrumbs' => [
        'enabled' => true,
    ],

    'links' => [
        // Generate absolute URLs for named routes (default: root-relative).
        'absolute' => false,
        // Add wire:navigate to internal links (Livewire SPA navigation).
        'wire_navigate' => false,
    ],

    'badges' => [
        // Cache store for badges with Item::BadgeTtl (null: the default store).
        'cache_store' => null,
    ],

    // NavigationContributor classes, resolved lazily.
    'contributors' => [],

    'diagnostics' => [
        // NavigationInspector warns above these.
        'max_nodes' => 200,
        'max_badges' => 10,
    ],

];
