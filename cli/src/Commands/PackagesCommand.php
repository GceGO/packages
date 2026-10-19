<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands;

use Gcego\Cli\Commands\Concerns\RendersOutput;
use Gcego\Cli\ExitCode;
use Gcego\Cli\Inspection\PackageInspector;
use Illuminate\Console\Command;
use Throwable;

/**
 * php artisan gcego:packages [--format=json]
 */
final class PackagesCommand extends Command
{
    use RendersOutput;

    protected $signature = 'gcego:packages {--format=text : Output format: text or json}';

    protected $description = 'Show every GCEGO package: version, constraint, registration and compatibility (exits 5 on problems)';

    public function handle(PackageInspector $inspector): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        try {
            $report = $inspector->report();
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }

        $missing = array_filter($report['packages'], static fn (array $package): bool => ($package['required'] ?? false) === true && ($package['integrated'] ?? false) !== true);
        $exit = $report['compatible'] && $missing === [] ? ExitCode::Success : ExitCode::MissingDependency;

        if ($this->json()) {
            $this->emit([...$report, 'exit_code' => $exit]);

            return $exit;
        }

        $this->table(['Package', 'Version', 'Constraint', 'Kind', 'Status'], array_map(static fn (array $package): array => [
            $package['id'],
            $package['version'] ?? '—',
            $package['constraint'] ?? '—',
            ($package['required'] ?? false) ? 'foundation' : 'optional',
            match (true) {
                ($package['installed'] ?? false) !== true => 'not installed',
                ($package['integrated'] ?? true) === true => 'integrated',
                default => 'NOT INTEGRATED',
            },
        ], $report['packages']));

        foreach ($report['problems'] as $problem) {
            $this->components->error($problem);
        }

        foreach ($report['notices'] as $notice) {
            $this->components->warn($notice);
        }

        $this->components->twoColumnDetail('Compatible', $report['compatible'] ? '<fg=green>yes</>' : '<fg=red>no</>');

        return $exit;
    }
}
