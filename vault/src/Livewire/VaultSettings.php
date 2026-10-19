<?php

declare(strict_types=1);

namespace Gcego\Vault\Livewire;

use Gcego\Vault\Permissions\VaultPermissions;
use Gcego\Vault\VaultConfig;
use Illuminate\Contracts\View\View;

/**
 * The effective Vault configuration, read-only. Settings live in
 * config/gcego-vault.php (validated at boot), so they change with a deploy,
 * never from a screen.
 */
final class VaultSettings extends VaultComponent
{
    protected function permission(): ?string
    {
        return VaultPermissions::SettingsView;
    }

    public function render(): View
    {
        $config = app(VaultConfig::class);
        $yes = __('On');
        $no = __('Off');

        return view('gcego-vault::livewire.settings', [...$this->shared(), 'sections' => [
            __('Integration') => [
                __('Answers gcego/security permissions') => $config->securityPermissions ? $yes : $no,
                __('Reports roles to gcego/security') => $config->securityRoles ? $yes : $no,
                __('Adds tenant roles to memberships') => $config->securityTenancy ? $yes : $no,
                __('Answers Gate checks of permission names') => $config->gate ? $yes : $no,
                __('Subject models') => implode(', ', $config->subjects) ?: '—',
            ],
            __('Permissions') => [
                __('Discover modules and resources') => $config->discoverModules ? $yes : $no,
                __('Discover panels') => $config->discoverPanels ? $yes : $no,
                __('Discover declared requirements') => $config->discoverRequirements ? $yes : $no,
                __('Resource abilities') => implode(', ', $config->abilities),
                __('Configured system roles') => implode(', ', array_keys($config->roles)) ?: '—',
            ],
            __('Governance') => [
                __('Super-administrator roles') => $config->superAdmin ? $yes : $no,
                __('Excluded from super administrators') => implode(', ', $config->superExcluded) ?: '—',
                __('Protected roles') => implode(', ', $config->protectedRoles) ?: '—',
                __('Sensitive access needs approval') => $config->sensitiveRequiresApproval ? $yes : $no,
                __('Approvals needed') => (string) $config->approvals,
                __('Approvals needed (sensitive)') => (string) $config->sensitiveApprovals,
                __('Longest temporary access') => $config->maxTemporaryDays === null ? __('No limit') : trans_choice(':count day|:count days', $config->maxTemporaryDays, ['count' => $config->maxTemporaryDays]),
                __('Access requests') => $config->requests ? $yes : $no,
                __('Open requests per person') => (string) $config->maxPending,
            ],
            __('Caching and audit') => [
                __('Permission cache') => $config->cache ? $yes : $no,
                __('Cache lifetime') => trans_choice(':count second|:count seconds', $config->cacheTtl, ['count' => $config->cacheTtl]),
                __('Audit log') => $config->audit ? $yes : $no,
                __('Audit retention') => $config->retentionDays === null ? __('Forever') : trans_choice(':count day|:count days', $config->retentionDays, ['count' => $config->retentionDays]),
            ],
        ]]);
    }
}
