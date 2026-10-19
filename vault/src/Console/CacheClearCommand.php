<?php

declare(strict_types=1);

namespace Gcego\Vault\Console;

use Gcego\Vault\Vault;
use Illuminate\Console\Command;

final class CacheClearCommand extends Command
{
    protected $signature = 'gcego:vault:cache-clear';

    protected $description = 'Invalidate every cached Vault permission set on every worker (rebuilt on next use)';

    public function handle(Vault $vault): int
    {
        $vault->flush();
        $this->components->info('Cached permissions were invalidated.');

        return self::SUCCESS;
    }
}
