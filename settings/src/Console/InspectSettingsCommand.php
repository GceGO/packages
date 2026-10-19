<?php

declare(strict_types=1);

namespace Gcego\Settings\Console;

use Gcego\Settings\Diagnostics\SettingsInspector;
use Illuminate\Console\Command;

final class InspectSettingsCommand extends Command
{
    protected $signature = 'gcego:settings:inspect {--json : Output JSON} {--definitions : Definitions only (no database)}';

    protected $description = 'Inspect settings definitions, stored values and configuration (never values)';

    public function handle(SettingsInspector $inspector): int
    {
        $report = $inspector->report(! $this->option('definitions'));

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report['problems'] === [] ? self::SUCCESS : self::FAILURE;
        }

        foreach ($report['groups'] as $group) {
            $settings = is_array($group['settings'] ?? null) ? $group['settings'] : [];
            $this->components->twoColumnDetail('<fg=green>'.(is_string($group['name'] ?? null) ? $group['name'] : '').'</>', count($settings).' settings');

            foreach (array_filter($settings, is_array(...)) as $setting) {
                $text = static fn (mixed $value): string => is_scalar($value) ? (string) $value : (is_array($value) ? implode(',', array_filter($value, is_string(...))) : '');
                $this->components->twoColumnDetail('  '.$text($setting['key'] ?? ''), $text($setting['type'] ?? '').' · '.$text($setting['scopes'] ?? []).(($setting['sensitive'] ?? false) === true ? ' · sensitive' : ''));
            }
        }

        if ($report['groups'] === []) {
            $this->components->info('No settings are registered.');
        }

        foreach ($report['warnings'] as $warning) {
            $this->components->warn($warning);
        }

        foreach ($report['problems'] as $problem) {
            $this->components->error($problem);
        }

        return $report['problems'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
