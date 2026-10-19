<?php

declare(strict_types=1);

namespace Gcego\Swatches\Console;

use Gcego\Swatches\Console\Concerns\FormatsOutput;
use Gcego\Swatches\Exceptions\SwatchesException;
use Gcego\Swatches\SwatchesManager;
use Illuminate\Console\Command;

/**
 * php artisan gcego:swatches:logo public/images/school-logo.png [--theme=ocean] [--format=json]
 */
final class LogoCommand extends Command
{
    use FormatsOutput;

    protected $signature = 'gcego:swatches:logo
        {path : A local PNG, JPEG, GIF, WebP or SVG logo}
        {--theme= : Theme the suggested brand is compiled over}
        {--format=text : Output format: text or json}';

    protected $description = 'Extract a logo\'s prominent colors and suggest an accessible tenant brand';

    public function handle(SwatchesManager $swatches): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        $themeOption = $this->option('theme');
        $theme = is_string($themeOption) && $themeOption !== '' ? $themeOption : null;

        try {
            $palette = $swatches->logoColors((string) $this->argument('path'));
            $brand = $palette->suggestBrand();
            $compiled = $swatches->compileBrand($brand, $theme);
        } catch (SwatchesException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->json()) {
            $this->emit([...$palette->toArray(), 'compiled' => ['light' => $compiled->light->toArray(), 'dark' => $compiled->dark->toArray(), 'notes' => $compiled->notes]]);

            return self::SUCCESS;
        }

        $this->components->info(sprintf('%d color(s) found (%s).', count($palette->colors), $palette->source));

        foreach ($palette->colors as $color) {
            $this->components->twoColumnDetail($color->color->toHex().($color->isNeutral() ? ' <fg=gray>(neutral)</>' : ''), number_format($color->share * 100, 1).'%');
        }

        $this->newLine();
        $this->components->twoColumnDetail('Suggested primary', $brand->primary?->toHex() ?? '— (no brand color in the logo)');
        $this->components->twoColumnDetail('Suggested accent', $brand->accent?->toHex() ?? '—');
        $this->components->twoColumnDetail('Light primary after contrast checks', $compiled->light->get('primary')?->toString() ?? '— (theme primary kept)');
        $this->components->twoColumnDetail('Dark primary after contrast checks', $compiled->dark->get('primary')?->toString() ?? '— (theme primary kept)');

        foreach ($compiled->notes as $note) {
            $this->components->warn($note);
        }

        return self::SUCCESS;
    }
}
