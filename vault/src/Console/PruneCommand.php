<?php

declare(strict_types=1);

namespace Gcego\Vault\Console;

use Gcego\Vault\Models\AuditEntry;
use Gcego\Vault\VaultConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

final class PruneCommand extends Command
{
    protected $signature = 'gcego:vault:prune';

    protected $description = 'Delete Vault audit entries older than gcego-vault.audit.retention_days (nothing without a retention period)';

    public function handle(VaultConfig $config): int
    {
        if ($config->retentionDays === null) {
            $this->components->info('No retention period is configured: audit entries are kept.');

            return self::SUCCESS;
        }

        $deleted = AuditEntry::query()->where('created_at', '<', Date::now()->subDays($config->retentionDays))->delete();
        $this->components->info(sprintf('%d audit entries deleted.', $deleted));

        return self::SUCCESS;
    }
}
