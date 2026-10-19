<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Console;

use Gcego\Luminaries\Console\Concerns\FormatsOutput;
use Gcego\Luminaries\Luminaries;
use Illuminate\Console\Command;

/**
 * php artisan gcego:icons:providers [--format=json]
 */
final class ProvidersCommand extends Command
{
    use FormatsOutput;

    protected $signature = 'gcego:icons:providers {--format=text : Output format: text or json}';

    protected $description = 'List the registered icon providers with their style, stroke support and icon count';

    public function handle(Luminaries $luminaries): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        $rows = [];

        foreach ($luminaries->providers() as $name => $provider) {
            $rows[] = [...$provider->metadata()->toArray(), 'icons' => count($luminaries->icons($name)), 'default' => $name === $luminaries->config()->defaultProvider];
        }

        if ($this->json()) {
            $this->emit(['providers' => $rows]);

            return self::SUCCESS;
        }

        $this->table(['Namespace', 'Label', 'Driver', 'Style', 'Stroke width', 'Icons'], array_map(static fn (array $row): array => [
            $row['name'].($row['default'] ? ' (default)' : ''),
            $row['label'],
            $row['driver'],
            $row['style'],
            $row['stroke_width'] ? 'yes' : 'no',
            (string) $row['icons'],
        ], $rows));

        return self::SUCCESS;
    }
}
