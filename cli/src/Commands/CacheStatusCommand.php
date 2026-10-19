<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands;

use Gcego\Cli\Commands\Concerns\RendersOutput;
use Gcego\Cli\ExitCode;
use Gcego\Cli\Inspection\CacheInspector;
use Illuminate\Console\Command;
use Throwable;

/**
 * php artisan gcego:cache-status [--format=json] — read-only; nothing is cleared.
 */
final class CacheStatusCommand extends Command
{
    use RendersOutput;

    protected $signature = 'gcego:cache-status {--format=text : Output format: text or json}';

    protected $description = 'Show the status of the caches GCEGO uses and how to clear each (read-only)';

    public function handle(CacheInspector $inspector): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        try {
            $rows = $inspector->report();
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }

        if ($this->json()) {
            $this->emit(['caches' => $rows]);

            return ExitCode::Success;
        }

        $this->table(['Cache', 'Status', 'Details', 'Clear with'], array_map(static fn (array $row): array => array_values($row), $rows));

        return ExitCode::Success;
    }
}
