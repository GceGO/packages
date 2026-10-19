<?php

declare(strict_types=1);

namespace Gcego\Studio\Console;

use Gcego\Studio\Studio;
use Illuminate\Console\Command;

/**
 * php artisan gcego:studio:inspect [--json] — what Studio assembled.
 */
final class InspectCommand extends Command
{
    protected $signature = 'gcego:studio:inspect {--json : Output JSON}';

    protected $description = 'Inspect the assembled GCEGO Admin Studio: packages, panels, modules, pages, assets and extensions';

    public function handle(Studio $studio): int
    {
        $report = $studio->inspect();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $text = static fn (mixed $value): string => is_scalar($value) ? (is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value) : (string) json_encode($value);

        $this->components->info('GCEGO Admin Studio '.$text(is_array($report['studio'] ?? null) ? ($report['studio']['version'] ?? '') : ''));

        $this->components->twoColumnDetail('<fg=green>Packages</>');

        foreach (is_array($report['packages'] ?? null) ? $report['packages'] : [] as $package) {
            if (is_array($package) && ($package['installed'] ?? false) === true) {
                $this->components->twoColumnDetail('  '.$text($package['id'] ?? ''), $text($package['version'] ?? '').' '.(($package['integrated'] ?? false) === true ? '<fg=green>integrated</>' : '<fg=red>not integrated</>'));
            }
        }

        foreach (['panels' => 'identity', 'modules' => 'module', 'pages' => 'identity', 'navigation' => 'identity'] as $section => $key) {
            $entries = is_array($report[$section] ?? null) ? $report[$section] : [];
            $this->components->twoColumnDetail('<fg=green>'.ucfirst($section).'</>', (string) count($entries));

            foreach ($entries as $entry) {
                if (is_array($entry)) {
                    $this->components->twoColumnDetail('  '.$text($entry[$key] ?? '?'), ($entry['healthy'] ?? true) === true ? '<fg=green>ok</>' : '<fg=red>problems</>');
                }
            }
        }

        $this->components->twoColumnDetail('<fg=green>Extensions</>', implode(', ', is_array($report['extensions'] ?? null) ? array_map($text, $report['extensions']) : []) ?: 'none');

        return self::SUCCESS;
    }
}
