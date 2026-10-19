<?php

declare(strict_types=1);

namespace Gcego\Vault\Console;

use Gcego\Vault\Models\Permission;
use Illuminate\Console\Command;

final class PermissionsCommand extends Command
{
    protected $signature = 'gcego:vault:permissions {--group= : Only this group} {--search= : Names or labels containing this text}';

    protected $description = 'List the permission registry: names, groups, scope, source, dependencies and how many roles grant each';

    public function handle(): int
    {
        $search = $this->option('search');
        $permissions = Permission::query()->withCount('roles')
            ->when(is_string($this->option('group')), fn ($query) => $query->where('group', $this->option('group')))
            ->when(is_string($search) && $search !== '', static fn ($query) => $query->where(static fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('label', 'like', '%'.$search.'%')))
            ->orderBy('group')->orderBy('name')->get();

        $this->table(['Permission', 'Group', 'Scope', 'Source', 'Sensitive', 'Requires', 'Roles'], $permissions->map(static fn (Permission $permission): array => [
            $permission->name, $permission->group ?? '—', $permission->scope, $permission->source, $permission->is_sensitive ? 'yes' : '', implode(', ', $permission->dependencies()), $permission->roles_count,
        ])->all());

        return self::SUCCESS;
    }
}
