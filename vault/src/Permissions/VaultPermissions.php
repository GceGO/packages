<?php

declare(strict_types=1);

namespace Gcego\Vault\Permissions;

/**
 * Vault's own permissions: who may see and change access. Registered by
 * gcego:vault:sync like any other permission, and held by nobody until
 * granted (gcego:vault:install can assign a first administrator).
 */
final class VaultPermissions
{
    public const string RolesView = 'vault.roles.viewAny';

    public const string RolesCreate = 'vault.roles.create';

    public const string RolesUpdate = 'vault.roles.update';

    public const string RolesDelete = 'vault.roles.delete';

    public const string RolesAssign = 'vault.roles.assign';

    public const string RolesExport = 'vault.roles.export';

    public const string RolesImport = 'vault.roles.import';

    public const string PermissionsView = 'vault.permissions.viewAny';

    public const string PermissionsManage = 'vault.permissions.manage';

    public const string PermissionsGrant = 'vault.permissions.grant';

    public const string UsersView = 'vault.users.viewAny';

    public const string RequestsReview = 'vault.requests.review';

    public const string AuditView = 'vault.audit.viewAny';

    public const string DiagnosticsView = 'vault.diagnostics.view';

    public const string SettingsView = 'vault.settings.view';

    /**
     * @return array<string, array{label: string, requires: list<string>, sensitive: bool}>
     */
    public static function definitions(): array
    {
        return [
            self::RolesView => ['label' => 'View roles', 'requires' => [], 'sensitive' => false],
            self::RolesCreate => ['label' => 'Create roles', 'requires' => [self::RolesView], 'sensitive' => false],
            self::RolesUpdate => ['label' => 'Edit roles and their permissions', 'requires' => [self::RolesView, self::PermissionsView], 'sensitive' => true],
            self::RolesDelete => ['label' => 'Archive and delete roles', 'requires' => [self::RolesView], 'sensitive' => false],
            self::RolesAssign => ['label' => 'Assign and revoke roles', 'requires' => [self::RolesView, self::UsersView], 'sensitive' => true],
            self::RolesExport => ['label' => 'Export role definitions', 'requires' => [self::RolesView], 'sensitive' => false],
            self::RolesImport => ['label' => 'Import role definitions', 'requires' => [self::RolesCreate, self::RolesUpdate], 'sensitive' => true],
            self::PermissionsView => ['label' => 'View the permission registry', 'requires' => [], 'sensitive' => false],
            self::PermissionsManage => ['label' => 'Register and edit permissions', 'requires' => [self::PermissionsView], 'sensitive' => true],
            self::PermissionsGrant => ['label' => 'Grant and revoke direct permissions', 'requires' => [self::PermissionsView, self::UsersView], 'sensitive' => true],
            self::UsersView => ['label' => 'View people\'s roles and effective permissions', 'requires' => [], 'sensitive' => false],
            self::RequestsReview => ['label' => 'Review access requests', 'requires' => [self::UsersView], 'sensitive' => true],
            self::AuditView => ['label' => 'View the access audit log', 'requires' => [], 'sensitive' => false],
            self::DiagnosticsView => ['label' => 'Run permission diagnostics', 'requires' => [self::UsersView], 'sensitive' => false],
            self::SettingsView => ['label' => 'View Vault settings', 'requires' => [], 'sensitive' => false],
        ];
    }
}
