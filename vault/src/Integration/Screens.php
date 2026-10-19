<?php

declare(strict_types=1);

namespace Gcego\Vault\Integration;

use Gcego\Vault\Permissions\VaultPermissions;

/**
 * The Vault screens: route suffix, path, Livewire component, the Vault
 * permission that opens it (null: anyone signed in), navigation label and icon.
 */
final class Screens
{
    /**
     * @return array<string, array{path: string, component: string, permission: string|null, label: string, icon: string, nav: bool}>
     */
    public static function all(): array
    {
        return [
            'roles' => ['path' => '', 'component' => 'gcego-vault-roles', 'permission' => VaultPermissions::RolesView, 'label' => 'roles', 'icon' => 'authentication.security', 'nav' => true],
            'roles.create' => ['path' => 'roles/create', 'component' => 'gcego-vault-role-editor', 'permission' => VaultPermissions::RolesCreate, 'label' => 'create_role', 'icon' => 'actions.create', 'nav' => false],
            'roles.show' => ['path' => 'roles/{role}', 'component' => 'gcego-vault-role-details', 'permission' => VaultPermissions::RolesView, 'label' => 'role', 'icon' => 'authentication.security', 'nav' => false],
            'roles.edit' => ['path' => 'roles/{role}/edit', 'component' => 'gcego-vault-role-editor', 'permission' => VaultPermissions::RolesUpdate, 'label' => 'edit_role', 'icon' => 'actions.edit', 'nav' => false],
            'permissions' => ['path' => 'permissions', 'component' => 'gcego-vault-permissions', 'permission' => VaultPermissions::PermissionsView, 'label' => 'permissions', 'icon' => 'status.locked', 'nav' => true],
            'matrix' => ['path' => 'matrix', 'component' => 'gcego-vault-matrix', 'permission' => VaultPermissions::RolesView, 'label' => 'matrix', 'icon' => 'tables.columns', 'nav' => true],
            'people' => ['path' => 'people', 'component' => 'gcego-vault-people', 'permission' => VaultPermissions::UsersView, 'label' => 'people', 'icon' => 'navigation.users', 'nav' => true],
            'people.show' => ['path' => 'people/{holder}', 'component' => 'gcego-vault-person', 'permission' => VaultPermissions::UsersView, 'label' => 'person', 'icon' => 'authentication.user', 'nav' => false],
            'temporary' => ['path' => 'temporary', 'component' => 'gcego-vault-temporary', 'permission' => VaultPermissions::UsersView, 'label' => 'temporary', 'icon' => 'status.pending', 'nav' => true],
            'requests' => ['path' => 'requests', 'component' => 'gcego-vault-requests', 'permission' => null, 'label' => 'requests', 'icon' => 'workflows.assign', 'nav' => true],
            'approvals' => ['path' => 'approvals', 'component' => 'gcego-vault-approvals', 'permission' => VaultPermissions::RequestsReview, 'label' => 'approvals', 'icon' => 'workflows.approve', 'nav' => true],
            'audit' => ['path' => 'audit', 'component' => 'gcego-vault-audit', 'permission' => VaultPermissions::AuditView, 'label' => 'audit', 'icon' => 'workflows.history', 'nav' => true],
            'diagnostics' => ['path' => 'diagnostics', 'component' => 'gcego-vault-diagnostics', 'permission' => VaultPermissions::DiagnosticsView, 'label' => 'diagnostics', 'icon' => 'actions.search', 'nav' => true],
            'settings' => ['path' => 'settings', 'component' => 'gcego-vault-settings', 'permission' => VaultPermissions::SettingsView, 'label' => 'settings', 'icon' => 'settings.settings', 'nav' => true],
        ];
    }
}
