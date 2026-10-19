<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Console;

use Gcego\Luminaries\Console\Concerns\FormatsOutput;
use Gcego\Luminaries\Diagnostics\Diagnostic;
use Gcego\Luminaries\Diagnostics\IconInspector;
use Illuminate\Console\Command;

/**
 * php artisan gcego:icons:check [--format=json]   (exits 1 when there are errors)
 */
final class CheckCommand extends Command
{
    use FormatsOutput;

    protected $signature = 'gcego:icons:check {--format=text : Output format: text or json}';

    protected $description = 'Validate icon providers, semantic aliases, custom SVG collections and the icon cache';

    public function handle(IconInspector $inspector): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        $diagnostics = $inspector->inspect();
        $errors = array_filter($diagnostics, static fn (Diagnostic $diagnostic): bool => $diagnostic->level === Diagnostic::Error);
        $exit = $errors === [] ? self::SUCCESS : self::FAILURE;

        if ($this->json()) {
            $this->emit(['ok' => $errors === [], 'diagnostics' => array_map(static fn (Diagnostic $diagnostic): array => $diagnostic->toArray(), $diagnostics)]);

            return $exit;
        }

        foreach ($diagnostics as $diagnostic) {
            $line = sprintf('[%s] %s%s', $diagnostic->area, $diagnostic->subject !== null ? $diagnostic->subject.': ' : '', $diagnostic->message);

            match ($diagnostic->level) {
                Diagnostic::Error => $this->components->error($line),
                Diagnostic::Warning => $this->components->warn($line),
                default => $this->components->info($line),
            };
        }

        $errors === []
            ? $this->components->info(sprintf('Icons OK (%d notices).', count($diagnostics)))
            : $this->components->error(sprintf('%d icon errors found.', count($errors)));

        return $exit;
    }
}
