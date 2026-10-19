<?php

declare(strict_types=1);

namespace Gcego\Vault\Console;

use Gcego\Vault\Vault;
use Illuminate\Console\Command;

final class SyncCommand extends Command
{
    protected $signature = 'gcego:vault:sync {--prune : Delete declared permissions that are no longer declared and that nobody holds}';

    protected $description = 'Register declared and discovered permissions and create or update the configured system roles (grants nothing)';

    public function handle(Vault $vault): int
    {
        $result = $vault->sync(prune: (bool) $this->option('prune'));

        foreach (['created' => 'Registered', 'updated' => 'Updated', 'pruned' => 'Pruned', 'stale' => 'No longer declared'] as $key => $label) {
            if ($result['permissions'][$key] !== []) {
                $this->components->twoColumnDetail($label.' permissions', (string) count($result['permissions'][$key]));
            }
        }

        foreach (['created' => 'Created', 'updated' => 'Updated', 'orphaned' => 'No longer configured'] as $key => $label) {
            if ($result['roles'][$key] !== []) {
                $this->components->twoColumnDetail($label.' roles', implode(', ', $result['roles'][$key]));
            }
        }

        $this->components->info('Vault is in sync with the application.');

        return self::SUCCESS;
    }
}
