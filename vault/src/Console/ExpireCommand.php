<?php

declare(strict_types=1);

namespace Gcego\Vault\Console;

use Gcego\Vault\Assignments\AssignmentManager;
use Illuminate\Console\Command;

final class ExpireCommand extends Command
{
    protected $signature = 'gcego:vault:expire';

    protected $description = 'Close and audit temporary access whose time ran out (it already stopped working when it expired)';

    public function handle(AssignmentManager $assignments): int
    {
        $this->components->info(sprintf('%d expired assignments and grants closed.', $assignments->expire()));

        return self::SUCCESS;
    }
}
