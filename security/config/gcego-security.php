<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Security
|--------------------------------------------------------------------------
|
| Publish with "php artisan vendor:publish --tag=gcego-security-config".
| Every value is validated at boot; unsafe values are rejected.
|
*/

return [

    'authorization' => [
        // The only accepted value. Anything without an explicit requirement is denied.
        'default' => 'deny',

        // Guard whose user is the actor. Null: Laravel's default guard.
        'guard' => null,

        // Status of ordinary denials: 403, or 404 to conceal every protected resource.
        'denial_status' => 403,
    ],

    'permissions' => [
        // "gate": Gate abilities (includes Spatie's Gate integration and the application's Gate::before rules).
        // "spatie": Spatie's hasPermissionTo() directly, without Gate::before.
        // Or a class implementing Gcego\Security\Contracts\PermissionProvider.
        'provider' => 'gate',
    ],

    'roles' => [
        // Global roles for RoleResolver: null (none), "spatie", or a RoleProvider class.
        'provider' => null,
    ],

    'tenancy' => [
        'enabled' => false,

        // A class implementing Gcego\Security\Contracts\TenantResolver (required when enabled).
        'resolver' => null,

        // Where ResolveTenant reads the requested tenant key: a route parameter, then the session.
        'parameter' => 'tenant',
        'session_key' => 'gcego.tenant',

        // Unknown tenants and tenants without membership: 404 (default) hides their existence.
        'denial_status' => 404,

        // Tenant-owned data for gcego/data sources: model class or interface => tenant key column.
        // These sources only return the active tenant's rows, and nothing without a tenant.
        'models' => [],
    ],

    'fields' => [
        // Replacement for redacted field values.
        'mask' => '********',
    ],

    'audit' => [
        'enabled' => false,

        // Log channel for LogAuditReporter. Null: the default channel.
        'channel' => null,

        // Report denials.
        'denials' => true,

        // Abilities and permissions (fnmatch patterns) reported when granted, e.g. ['*.delete', 'exports.*'].
        'sensitive' => [],
    ],

];
