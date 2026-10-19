<?php

declare(strict_types=1);

namespace Gcego\Vault\Console;

use Gcego\Vault\Console\Concerns\FindsHolders;
use Gcego\Vault\Enums\GrantSource;
use Gcego\Vault\Vault;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

final class AssignCommand extends Command
{
    use FindsHolders;

    protected $signature = 'gcego:vault:assign {holder : Key or email} {role} {--tenant= : Scope ("institution:7"); global by default} {--days= : Temporary: ends after this many days} {--revoke : Revoke instead} {--reason=}';

    protected $description = 'Give or revoke a role from the console (trusted; the recovery path when no administrator is left)';

    public function handle(Vault $vault): int
    {
        $holder = $this->findHolder((string) $this->argument('holder'));

        if ($holder === null) {
            $this->components->error('No account matches '.$this->argument('holder').'.');

            return self::FAILURE;
        }

        $tenant = Vault::scope(is_string($this->option('tenant')) ? $this->option('tenant') : null);
        $reason = is_string($this->option('reason')) ? $this->option('reason') : 'gcego:vault:assign';
        $role = (string) $this->argument('role');

        if ($this->option('revoke')) {
            $done = $vault->assignments()->revokeRoleTrusted($holder, $role, $tenant, $reason);
            $done ? $this->components->info('Role revoked.') : $this->components->warn('The account did not hold that role.');

            return self::SUCCESS;
        }

        $days = $this->option('days');
        $vault->assignments()->assignTrusted($holder, $role, $tenant, is_numeric($days) ? Date::now()->addDays((int) $days) : null, $reason, GrantSource::System);
        $this->components->info('Role assigned.');

        return self::SUCCESS;
    }
}
