<?php

declare(strict_types=1);

namespace Gcego\Core\Console;

use Gcego\Core\Gcego;
use Illuminate\Console\Command;

final class PlanCommand extends Command
{
    protected $signature = 'gcego:plan {--json : Output the startup plan as JSON}';

    protected $description = 'Preview GCEGO package and extension startup without running hooks';

    public function handle(Gcego $gcego): int
    {
        $plan = $gcego->plan();

        if ($this->option('json')) {
            $this->line(json_encode($plan->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        } else {
            $this->line($plan->enabled ? 'GCEGO is enabled.' : 'GCEGO is disabled or configuration is invalid.');
            $rows = [];

            foreach ($plan->packages as $id) {
                $rows[] = ['package', $id, 'eligible'];
            }

            foreach ($plan->extensions as $id) {
                $rows[] = ['extension', $id, 'eligible'];
            }

            foreach ($plan->disabledPackages as $id) {
                $rows[] = ['package', $id, 'disabled'];
            }

            foreach ($plan->skippedExtensions as $id => $reason) {
                $rows[] = ['extension', $id, $reason];
            }

            $this->table(['Kind', 'Identifier', 'Status'], $rows);

            foreach ($plan->problems as $problem) {
                $this->error($problem);
            }
        }

        return $plan->isValid() ? self::SUCCESS : self::FAILURE;
    }
}
