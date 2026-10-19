<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands;

use Gcego\Cli\Commands\Concerns\RendersOutput;
use Gcego\Cli\ExitCode;
use Gcego\Cli\Validation\DefinitionValidation;
use Illuminate\Console\Command;
use Throwable;

/**
 * php artisan gcego:validate [--type=blueprints] [--id=blueprint:subjects] [--format=json]
 */
final class ValidateCommand extends Command
{
    use RendersOutput;

    protected $signature = 'gcego:validate
        {--type= : blueprints, modules, resources, pages, panels, layouts, navigation, widgets, workflows, settings, analytics, or a registry kind}
        {--id= : One definition identity, e.g. blueprint:subjects}
        {--format=text : Output format: text or json}';

    protected $description = 'Validate every discovered GCEGO definition with its package\'s validators (exits 3 when one is invalid)';

    public function handle(DefinitionValidation $validation): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        try {
            $type = $this->option('type');
            $id = $this->option('id');
            $report = $validation->run(is_string($type) && $type !== '' ? $validation->kindsFor($type) : [], is_string($id) && $id !== '' ? $id : null);
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }

        $exit = $report->valid() ? ExitCode::Success : ExitCode::ValidationFailed;

        if ($this->json()) {
            $this->emit([...$report->toArray(), 'exit_code' => $exit]);

            return $exit;
        }

        foreach ($report->entries as $entry) {
            $this->components->twoColumnDetail(($entry->identity ?? '(not registered)').' <fg=gray>'.$entry->source.'</>', $entry->valid() ? '<fg=green>valid</>' : '<fg=red>invalid</>');

            foreach ($entry->problems as $problem) {
                $this->line(sprintf('    <fg=red>%s</> %s', $problem->code, str_replace("\n", "\n    ", $problem->message)));
            }
        }

        foreach ($report->notices as $notice) {
            $this->components->warn($notice);
        }

        $summary = sprintf('%d definitions, %d invalid (%.1f ms).', count($report->entries), count($report->invalid()), $report->durationMs);
        $report->valid() ? $this->components->info($summary) : $this->components->error($summary);

        return $exit;
    }
}
