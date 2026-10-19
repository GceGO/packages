<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Settings
|--------------------------------------------------------------------------
|
| Settings are declared in PHP (app/GCEGo/Settings, discovered by
| gcego/registry, kind "settings") and stored as overrides per scope
| (global, panel, tenant, user). Only registered settings can be written,
| every write is authorized on the server, validated, versioned and
| recorded in an append-only history, and sensitive values are encrypted
| before they reach the database, the cache or the history.
|
| Publish with "php artisan vendor:publish --tag=gcego-settings-config".
| Every value is validated when the configuration is first used.
|
*/

return [

    // false: no discovery, no panel pages and no Livewire components (stored values stay readable).
    'enabled' => true,

    'discovery' => [
        // Folders whose PHP files return a SettingsGroup (or a DefinitionProvider class), kind "settings".
        'paths' => [app_path('GCEGo/Settings')],
        // Folders whose PHP files return a SettingsPage, kind "settings-page". A group without a page gets a default one.
        'pages' => [app_path('GCEGo/SettingsPages')],
    ],

    'storage' => [
        // The only driver: Eloquent tables gcego_settings, gcego_setting_changes and gcego_setting_requests.
        'driver' => 'database',
        // Database connection of the settings tables (null: the default).
        'connection' => null,
    ],

    'cache' => [
        'enabled' => true,
        // Laravel cache store (null: the default). Use a shared store (redis, database, memcached) when several servers run.
        'store' => null,
        // Seconds a cached scope stays valid. Writes invalidate it immediately on every worker.
        'ttl' => 3600,
        'prefix' => 'gcego-settings',
    ],

    'scopes' => [
        // Scopes settings may be stored at. A setting still declares which of them it allows.
        'global' => true,
        'panel' => true,
        'tenant' => true,
        'user' => true,
        // Lookup order, most specific first. A setting may declare its own order (Setting::Precedence).
        'precedence' => ['user', 'tenant', 'panel', 'global'],
    ],

    'security' => [
        // Must stay true: every write and every settings screen is authorized on the server.
        'require_authorization' => true,
        // Must stay true: sensitive values are always encrypted at rest.
        'encrypt_sensitive_values' => true,
        // Must stay true: tenant values are only reachable inside their verified tenant.
        'enforce_tenant_scope' => true,
        // Laravel Gate ability allowed to manage global and panel settings (besides a group's own requirements).
        'manage_ability' => 'gcego-settings.manage',
        // Tenant permission (TenantMembership::grants) allowed to manage a tenant's own settings.
        'tenant_permission' => 'settings.manage',
        // Gate abilities that may export and import settings (on top of viewing or updating each one).
        'export_ability' => 'gcego-settings.export',
        'import_ability' => 'gcego-settings.import',
    ],

    'history' => [
        'enabled' => true,
        // Changes shown per page of the history.
        'per_page' => 20,
        // Longest previous/new value kept in the history (characters); sensitive values are never kept.
        'value_limit' => 2000,
    ],

    'approvals' => [
        // Route changes of settings declaring Setting::Approval through that gcego/workflows workflow.
        'enabled' => true,
    ],

    'notifications' => [
        // Tell requesters when an approved change is applied or refused, through gcego/notifications.
        'enabled' => true,
        // A class implementing Gcego\Settings\Contracts\SettingsRecipients, told about sensitive changes (null: nobody).
        'recipients' => null,
    ],

    'panels' => [
        // Add the settings pages ("<panel>/settings") to every gcego/panels panel.
        'enabled' => true,
        'path' => 'settings',
    ],

    'transfer' => [
        // Largest number of settings in one import.
        'max_settings' => 500,
    ],

    'observability' => [
        // A log channel for structured settings activity (null: Laravel events only).
        'log_channel' => null,
    ],

];
