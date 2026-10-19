<?php

declare(strict_types=1);

namespace Gcego\Vault\Console;

use Gcego\Vault\Models\Role;
use Illuminate\Console\Command;

final class RolesCommand extends Command
{
    protected $signature = 'gcego:vault:roles {--tenant= : Only roles of this tenant ("institution:7")} {--archived : Include archived roles}';

    protected $description = 'List Vault roles with their permissions, inheritance and active holders';

    public function handle(): int
    {
        $roles = Role::query()
            ->withCount(['permissions', 'assignments' => static fn ($query) => $query->active()])
            ->with('parents:id,name')
            ->when(is_string($this->option('tenant')), fn ($query) => $query->where('tenant', $this->option('tenant')))
            ->when(! $this->option('archived'), static fn ($query) => $query->whereNull('archived_at'))
            ->orderBy('tenant')->orderBy('name')
            ->get();

        $this->table(['Role', 'Label', 'Scope', 'Flags', 'Inherits', 'Permissions', 'Holders'], $roles->map(static fn (Role $role): array => [
            $role->name,
            $role->label,
            $role->tenant === '' ? 'global' : $role->tenant,
            implode(' ', array_filter([$role->is_system ? 'system' : null, $role->is_protected ? 'protected' : null, $role->is_super ? 'super' : null, $role->panel === null ? null : 'panel:'.$role->panel, $role->archived_at === null ? null : 'archived'])),
            $role->parents->pluck('name')->implode(', '),
            $role->permissions_count,
            $role->assignments_count,
        ])->all());

        return self::SUCCESS;
    }
}
