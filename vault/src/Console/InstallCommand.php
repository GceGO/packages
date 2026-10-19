<?php

declare(strict_types=1);

namespace Gcego\Vault\Console;

use Gcego\Vault\Console\Concerns\FindsHolders;
use Gcego\Vault\Enums\GrantSource;
use Gcego\Vault\Vault;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class InstallCommand extends Command
{
    use FindsHolders;

    protected $signature = 'gcego:vault:install
        {--admin= : Key or email of a first administrator to give --role to}
        {--role= : The role for --admin (default: the first protected or super role configured)}';

    protected $description = 'Install GCEGO Vault: check its tables, register permissions and system roles, optionally give a first administrator a role (never removes anything)';

    public function handle(Vault $vault): int
    {
        try {
            $ready = Schema::connection(config('gcego-vault.connection'))->hasTable('gcego_vault_roles');
        } catch (Throwable) {
            $ready = false;
        }

        if (! $ready) {
            $this->components->error('The Vault tables do not exist yet. Run "php artisan migrate" first (Vault adds tables only; it never changes existing ones).');

            return self::FAILURE;
        }

        $result = $vault->sync();
        $this->components->info(sprintf('Permissions: %d registered, %d updated, %d stale. Roles: %d created, %d updated.', count($result['permissions']['created']), count($result['permissions']['updated']), count($result['permissions']['stale']), count($result['roles']['created']), count($result['roles']['updated'])));

        $admin = $this->option('admin');

        if (is_string($admin) && $admin !== '') {
            $holder = $this->findHolder($admin);

            if ($holder === null) {
                $this->components->error('No account matches '.$admin.'.');

                return self::FAILURE;
            }

            $role = $this->option('role');
            $role = is_string($role) && $role !== '' ? $role : $this->defaultRole();

            if ($role === null) {
                $this->components->error('Name the role with --role (no protected or super role is configured).');

                return self::FAILURE;
            }

            $vault->assignments()->assignTrusted($holder, $role, reason: 'gcego:vault:install', source: GrantSource::System);
            $this->components->info(sprintf('Gave %s the role %s.', $admin, $role));
        }

        return self::SUCCESS;
    }

    private function defaultRole(): ?string
    {
        foreach (config('gcego-vault.roles', []) as $name => $definition) {
            if (is_array($definition) && (($definition['protected'] ?? false) === true || ($definition['super'] ?? false) === true)) {
                return (string) $name;
            }
        }

        return null;
    }
}
