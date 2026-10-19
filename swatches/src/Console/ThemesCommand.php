<?php

declare(strict_types=1);

namespace Gcego\Swatches\Console;

use Gcego\Swatches\Console\Concerns\FormatsOutput;
use Gcego\Swatches\SwatchesManager;
use Illuminate\Console\Command;

/**
 * php artisan gcego:swatches:themes [--format=json]
 */
final class ThemesCommand extends Command
{
    use FormatsOutput;

    protected $signature = 'gcego:swatches:themes {--format=text : Output format: text or json}';

    protected $description = 'List registered color themes, their inheritance and validation status';

    public function handle(SwatchesManager $swatches): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        $rows = [];

        foreach ($swatches->themes() as $name => $theme) {
            $report = $swatches->validate($name);
            $rows[] = [
                'name' => $name,
                'label' => $theme->label(),
                'extends' => $theme->extends,
                'source' => $theme->source->value,
                'default' => $name === $swatches->defaultTheme(),
                'overrides' => ['light' => count($theme->light), 'dark' => count($theme->dark)],
                'valid' => $report->passes(),
                'errors' => count($report->errors()),
                'warnings' => count($report->warnings()),
            ];
        }

        if ($this->json()) {
            $this->emit(['default' => $swatches->defaultTheme(), 'themes' => $rows]);

            return self::SUCCESS;
        }

        $this->table(['Theme', 'Label', 'Extends', 'Source', 'Overrides (light/dark)', 'Status'], array_map(static fn (array $row): array => [
            $row['name'].($row['default'] ? ' (default)' : ''),
            $row['label'],
            $row['extends'] ?? '—',
            $row['source'],
            $row['overrides']['light'].' / '.$row['overrides']['dark'],
            $row['valid'] ? ($row['warnings'] > 0 ? 'valid, '.$row['warnings'].' warning(s)' : 'valid') : $row['errors'].' error(s)',
        ], $rows));

        return self::SUCCESS;
    }
}
