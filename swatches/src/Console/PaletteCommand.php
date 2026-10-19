<?php

declare(strict_types=1);

namespace Gcego\Swatches\Console;

use Gcego\Swatches\Console\Concerns\FormatsOutput;
use Gcego\Swatches\Exceptions\SwatchesException;
use Gcego\Swatches\SwatchesManager;
use Illuminate\Console\Command;

/**
 * php artisan gcego:swatches:palette "#2563EB" --name=brand [--format=json]
 */
final class PaletteCommand extends Command
{
    use FormatsOutput;

    protected $signature = 'gcego:swatches:palette
        {color : The brand color}
        {--name=brand : Palette name}
        {--format=text : Output format: text or json}';

    protected $description = 'Generate an 11-step OKLCH palette from one brand color';

    public function handle(SwatchesManager $swatches): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        try {
            $palette = $swatches->generatePalette((string) $this->option('name'), (string) $this->argument('color'));
        } catch (SwatchesException $exception) {
            $this->components->error($exception->getMessage());

            return self::INVALID;
        }

        if ($this->json()) {
            $this->emit(['name' => $palette->name, 'anchor' => (int) ($palette->metadata['anchor'] ?? 0), 'steps' => $palette->toArray()]);

            return self::SUCCESS;
        }

        foreach ($palette->colors as $step => $color) {
            $this->components->twoColumnDetail(
                $step.((string) $step === ($palette->metadata['anchor'] ?? null) ? ' (brand)' : ''),
                $color->toHex().'  '.$color->toOklchString(),
            );
        }

        $this->newLine();
        $this->line(sprintf("  Add it to config/gcego-swatches.php: 'palettes' => ['%s' => '%s'],", $palette->name, $palette->metadata['brand'] ?? ''));

        return self::SUCCESS;
    }
}
