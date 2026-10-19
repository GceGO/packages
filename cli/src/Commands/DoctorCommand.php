<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands;

use Gcego\Cli\CliConfig;
use Gcego\Cli\Commands\Concerns\RendersOutput;
use Gcego\Cli\Diagnostics\DiagnosticStatus;
use Gcego\Cli\Diagnostics\Doctor;
use Gcego\Cli\ExitCode;
use Illuminate\Console\Command;
use Throwable;

/**
 * php artisan gcego:doctor [--database] [--production] [--format=json] — read-only.
 */
final class DoctorCommand extends Command
{
    use RendersOutput;

    protected $signature = 'gcego:doctor
        {--database : Also connect to the database and read the migrations table}
        {--production : Add the production readiness checks}
        {--format=text : Output format: text or json}';

    protected $description = 'Diagnose the GCEGO installation (read-only; exits 6 when a check fails)';

    public function handle(Doctor $doctor, CliConfig $config): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        if (! $config->diagnostics) {
            $this->components->warn('Diagnostics are disabled (gcego-cli.diagnostics.enabled).');

            return ExitCode::Success;
        }

        try {
            $report = $doctor->run((bool) $this->option('database'), (bool) $this->option('production'));
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }

        $exit = $report->healthy() ? ExitCode::Success : ExitCode::DiagnosticFailure;

        if ($this->json()) {
            $this->emit([...$report->toArray(), 'exit_code' => $exit]);

            return $exit;
        }

        $this->newLine();
        $this->line('<options=bold>GCEGO ADMIN STUDIO — SYSTEM DIAGNOSTICS</>');
        $this->newLine();

        foreach ($report->results as $result) {
            $this->components->twoColumnDetail($result->label.' <fg=gray>'.$result->id.'</>', match ($result->status) {
                DiagnosticStatus::Pass => '<fg=green>PASS</>',
                DiagnosticStatus::Warning => '<fg=yellow>WARNING</>',
                DiagnosticStatus::Failure => '<fg=red>FAIL</>',
                DiagnosticStatus::Skipped => '<fg=gray>SKIPPED</>',
                DiagnosticStatus::NotApplicable => '<fg=gray>N/A</>',
            });

            if (in_array($result->status, [DiagnosticStatus::Warning, DiagnosticStatus::Failure], true) || $this->output->isVerbose()) {
                $this->line('    '.$result->message);

                if ($result->remediation !== null) {
                    $this->line('    <fg=gray>→ '.$result->remediation.'</>');
                }
            }
        }

        $this->newLine();
        $this->line(sprintf('Overall Status: %s (%d passed, %d warnings, %d failed, %d skipped, %d not applicable)', $report->healthy() ? '<fg=green;options=bold>HEALTHY</>' : '<fg=red;options=bold>UNHEALTHY</>', $report->count(DiagnosticStatus::Pass), $report->count(DiagnosticStatus::Warning), $report->count(DiagnosticStatus::Failure), $report->count(DiagnosticStatus::Skipped), $report->count(DiagnosticStatus::NotApplicable)));

        return $exit;
    }
}
