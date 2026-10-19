<?php

declare(strict_types=1);

namespace Gcego\Analytics\Console;

use Gcego\Analytics\Exports\ExportManager;
use Illuminate\Console\Command;

/**
 * Deletes expired export files (registered on Laravel's scheduler hourly).
 */
final class PruneExportsCommand extends Command
{
    protected $signature = 'gcego:analytics:prune-exports';

    protected $description = 'Delete expired analytics export files';

    public function handle(ExportManager $exports): int
    {
        $this->components->info(sprintf('Expired %d export(s).', $exports->prune()));

        return self::SUCCESS;
    }
}
