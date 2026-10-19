<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands;

use Gcego\Cli\Commands\Concerns\RendersOutput;
use Gcego\Cli\ExitCode;
use Gcego\Studio\Assets\StudioAssetManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * php artisan gcego:assets [--format=json] — gcego/studio's asset inspection; nothing is built.
 */
final class AssetsCommand extends Command
{
    use RendersOutput;

    protected $signature = 'gcego:assets {--format=text : Output format: text or json}';

    protected $description = 'Inspect the Vite manifest, Tailwind CSS v4 and gcego/chroma integration (read-only; exits 6 on problems)';

    public function handle(StudioAssetManager $assets): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        try {
            $report = $assets->inspect();
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }

        $exit = $report->problems === [] ? ExitCode::Success : ExitCode::DiagnosticFailure;

        if ($this->json()) {
            $this->emit([...$report->toArray(), 'exit_code' => $exit]);

            return $exit;
        }

        $flag = static fn (bool $ok, string $yes = 'yes', string $no = 'no'): string => $ok ? '<fg=green>'.$yes.'</>' : '<fg=yellow>'.$no.'</>';
        $this->components->twoColumnDetail('Vite entries', implode(', ', $report->entries) ?: 'none configured');
        $this->components->twoColumnDetail('Vite manifest', $flag($report->manifestFound, 'found', 'missing'));
        $this->components->twoColumnDetail('Vite dev server', $report->devServer ? 'running (public/hot)' : 'not running');
        $this->components->twoColumnDetail('Tailwind CSS v4 import', $flag($report->tailwind4));
        $this->components->twoColumnDetail('gcego/chroma import', $flag($report->stylingImported));
        $this->components->twoColumnDetail('Second Alpine instance', $report->duplicateAlpine ? '<fg=yellow>yes</>' : '<fg=green>no</>');

        foreach ($report->problems as $problem) {
            $this->components->error($problem);
        }

        foreach ($report->warnings as $warning) {
            $this->components->warn($warning);
        }

        return $exit;
    }
}
