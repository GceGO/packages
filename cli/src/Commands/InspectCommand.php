<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands;

use Gcego\Cli\Commands\Concerns\RendersOutput;
use Gcego\Cli\ExitCode;
use Gcego\Cli\Inspection\DefinitionReporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * php artisan gcego:inspect module:academics [--format=json]
 */
final class InspectCommand extends Command
{
    use RendersOutput;

    protected $signature = 'gcego:inspect
        {identity : The definition identity, e.g. blueprint:subjects, module:academics, panel:admin}
        {--format=text : Output format: text or json}';

    protected $description = 'Inspect one GCEGO definition: source, properties, children, validation and integration (read-only)';

    public function handle(DefinitionReporter $reporter): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        try {
            $identity = $this->argument('identity');
            $report = $reporter->inspect(is_string($identity) ? $identity : '');
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }

        $exit = ($report['valid'] ?? false) === true ? ExitCode::Success : ExitCode::ValidationFailed;

        if ($this->json()) {
            $this->emit($report);

            return $exit;
        }

        $this->components->info((string) json_encode($report['identity']).' — '.(string) json_encode($report['class']));
        $this->section($report, ['kind', 'name', 'scope', 'source', 'valid']);

        foreach (['properties', 'requirements', 'children', 'problems', 'integration', 'sections', 'metadata'] as $section) {
            if (! empty($report[$section])) {
                $this->line('<fg=green>'.ucfirst($section).'</>');
                $this->line('  '.str_replace("\n", "\n  ", (string) json_encode($report[$section], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));
            }
        }

        return $exit;
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  list<string>  $keys
     */
    private function section(array $report, array $keys): void
    {
        foreach ($keys as $key) {
            $value = $report[$key] ?? null;
            $this->components->twoColumnDetail(ucfirst($key), is_bool($value) ? ($value ? '<fg=green>yes</>' : '<fg=red>no</>') : (is_scalar($value) ? (string) $value : '—'));
        }
    }
}
