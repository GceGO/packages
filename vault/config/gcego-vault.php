<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Vault
|--------------------------------------------------------------------------
|
| Publish with "php artisan vendor:publish --tag=gcego-vault-config".
| Every value is validated at boot; unsafe values are rejected.
|
| Vault stores roles, permissions and assignments and answers gcego/security's
| permission questions. Nothing here grants access by itself: discovered
| permissions are registered, never assigned.
|
*/

return [

    'enabled' => true,

    // Database connection of the vault_* tables. Null: the default connection.
    'connection' => null,

    // Eloquent models that can hold roles and permissions (the HasVaultAccess trait). Null: auth.providers.users.model.
    'subjects' => null,

    'security' => [
        // Answer gcego/security's global permissions (Permission requirements). Permissions Vault does not know
        // still go to the previously configured provider (gate or spatie), so existing abilities keep working.
        'permissions' => true,

        // Report Vault's global roles to gcego/security's RoleResolver.
        'roles' => true,

        // Add Vault's tenant-scoped roles and permissions to memberships the application's TenantResolver verified.
        // Vault never creates a membership: a user who is not a member of a tenant gets nothing there.
        'tenancy' => true,

        // Answer Gate checks of Vault permission names ($user->can('reports.export')) when no policy, Gate::define
        // or before-callback answered. A policy's own answer is never replaced.
        'gate' => true,
    ],

    'permissions' => [
        // Permissions registered from code: name => [label, description, group, scope (global|tenant|any), sensitive, requires].
        'definitions' => [],

        'discovery' => [
            // modules.<module>.access and <resource>.<ability> for every gcego/modules resource.
            'modules' => true,
            // panels.<panel>.access for every gcego/panels panel.
            'panels' => true,
            // Permission requirements declared anywhere in module definitions (fields, columns, actions, pages).
            'requirements' => true,
        ],

        // Resource abilities registered for each discovered resource (Laravel policy method names).
        'abilities' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'],

        // Permissions an ability needs alongside it: an update grant without view is rejected.
        'dependencies' => [
            'view' => ['viewAny'],
            'create' => ['viewAny'],
            'update' => ['view'],
            'delete' => ['view'],
            'restore' => ['view'],
            'forceDelete' => ['view'],
        ],
    ],

    // System roles synchronized by gcego:vault:sync: name => [label, description, permissions (names or patterns such
    // as "subjects.*" or "*"), inherits, protected, sync (create: only when created; merge: add missing; replace: exact)].
    'roles' => [],

    'super_admin' => [
        // Roles flagged "super" grant every registered global permission, except the excluded patterns. They never
        // grant tenant permissions, never replace a policy's answer and never bypass the governance rules below.
        'enabled' => false,
        'excluded' => [],
    ],

    'governance' => [
        // Roles whose last active holder cannot be removed (revoked, expired early, archived or deleted).
        'protected_roles' => [],

        // Sensitive roles and permissions can only be given through an approved access request.
        'sensitive_requires_approval' => false,

        // Distinct approvers an access request needs (never the requester or the beneficiary), and for sensitive access.
        'approvals' => 1,
        'sensitive_approvals' => 2,

        // Longest temporary access, in days. Null: no limit.
        'max_temporary_days' => 365,
    ],

    'requests' => [
        'enabled' => true,
        // Open requests one person may have at a time.
        'max_pending' => 10,
    ],

    'cache' => [
        'enabled' => true,
        // Laravel cache store. Null: the default store.
        'store' => null,
        // Seconds an effective permission set may be cached; temporary access always shortens it to its expiry.
        'ttl' => 3600,
        'prefix' => 'gcego-vault',
    ],

    'audit' => [
        'enabled' => true,
        // Days entries are kept by gcego:vault:prune. Null: forever.
        'retention_days' => null,
    ],

    'panels' => [
        // The Vault screens in gcego/panels panels ("<panel>/vault/..."), each behind its own Vault permission.
        'enabled' => true,
        'path' => 'vault',
        'navigation_group' => 'Access control',
        // Panel names that get the screens. Null: every panel.
        'only' => null,
    ],

];
