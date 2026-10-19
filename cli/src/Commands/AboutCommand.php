<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands;

use Gcego\Cli\Commands\Concerns\CommandNames;
use Gcego\Cli\Commands\Concerns\RendersOutput;
use Gcego\Cli\ExitCode;
use Gcego\Cli\Inspection\Catalog;
use Gcego\Studio\Studio;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Throwable;

/**
 * php artisan gcego:about [--format=json]
 */
final class AboutCommand extends Command
{
    use RendersOutput;

    protected $signature = 'gcego:about {--format=text : Output format: text or json}';

    protected $description = 'Summarize the GCEGO installation: versions, packages, definitions and commands';

    public function handle(Studio $studio, Catalog $catalog, Kernel $kernel): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        try {
            $packages = $studio->packages();
            $counts = [];

            foreach ($catalog->descriptors() as $descriptor) {
                $counts[$descriptor->kind()] = ($counts[$descriptor->kind()] ?? 0) + 1;
            }

            ksort($counts);
            $commands = array_values(array_filter(array_keys($kernel->all()), static fn (string $name): bool => str_starts_with($name, 'gcego:')));
            sort($commands);
            $about = [
                'studio' => Studio::version(),
                'cli' => CommandNames::Version,
                'php' => PHP_VERSION,
                'laravel' => $this->laravel->version(),
                'environment' => $this->laravel->environment(),
                'packages' => ['installed' => count(array_filter($packages, static fn ($package): bool => $package->installed)), 'integrated' => count(array_filter($packages, static fn ($package): bool => $package->integrated()))],
                'capabilities' => $studio->capabilities(),
                'definitions' => $counts,
                'commands' => $commands,
            ];
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }

        if ($this->json()) {
            $this->emit($about);

            return ExitCode::Success;
        }

        $this->components->info('GCEGO Admin Studio '.$about['studio']);

        foreach (['cli' => 'gcego/cli', 'php' => 'PHP', 'laravel' => 'Laravel', 'environment' => 'Environment'] as $key => $label) {
            $this->components->twoColumnDetail($label, (string) $about[$key]);
        }

        $this->components->twoColumnDetail('Packages', sprintf('%d installed, %d integrated', $about['packages']['installed'], $about['packages']['integrated']));
        $this->components->twoColumnDetail('Definitions', implode(', ', array_map(static fn (string $kind, int $count): string => $kind.': '.$count, array_keys($counts), $counts)) ?: 'none');
        $this->components->twoColumnDetail('Commands', count($commands).' gcego:* (php artisan list gcego)');

        return ExitCode::Success;
    }
}
