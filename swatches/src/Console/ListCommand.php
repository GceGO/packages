<?php

declare(strict_types=1);

namespace Gcego\Swatches\Console;

use Gcego\Swatches\Console\Concerns\FormatsOutput;
use Gcego\Swatches\Palettes\Palette;
use Gcego\Swatches\SwatchesManager;
use Gcego\Swatches\Tokens\SemanticTokens;
use Illuminate\Console\Command;

/**
 * php artisan gcego:swatches:list [palettes|tokens] [--theme=ocean] [--format=json]
 */
final class ListCommand extends Command
{
    use FormatsOutput;

    protected $signature = 'gcego:swatches:list
        {type=all : palettes, tokens or all}
        {--theme= : Show token values of this theme (default: the configured theme)}
        {--format=text : Output format: text or json}';

    protected $description = 'List registered palettes and the semantic color tokens';

    public function handle(SwatchesManager $swatches): int
    {
        if (($invalid = $this->invalidFormat()) !== null) {
            return $invalid;
        }

        $type = (string) $this->argument('type');

        if (! in_array($type, ['all', 'palettes', 'tokens'], true)) {
            $this->components->error('The type must be "palettes", "tokens" or "all".');

            return self::INVALID;
        }

        $themeOption = $this->option('theme');
        $theme = $swatches->resolve(is_string($themeOption) && $themeOption !== '' ? $themeOption : null);

        $palettes = array_map(static fn (Palette $palette): array => [
            'name' => $palette->name,
            'label' => $palette->label(),
            'source' => $palette->metadata['source'] ?? 'custom',
            'steps' => $palette->toArray(),
        ], array_values($swatches->palettes()));

        $tokens = [];

        foreach (SemanticTokens::all() as $name => $token) {
            $tokens[] = [
                'name' => $name,
                'group' => $token->group->value,
                'property' => '--gcego-'.$name,
                'light' => $theme->light->get($name)?->toString(),
                'dark' => $theme->dark->get($name)?->toString(),
                'resolved_light' => $theme->color($name)->toHex(),
                'resolved_dark' => $theme->color($name, true)->toHex(),
                'description' => $token->description,
            ];
        }

        if ($this->json()) {
            $this->emit(array_filter([
                'theme' => $theme->name(),
                'palettes' => $type === 'tokens' ? null : $palettes,
                'tokens' => $type === 'palettes' ? null : $tokens,
            ], static fn (mixed $value): bool => $value !== null));

            return self::SUCCESS;
        }

        if ($type !== 'tokens') {
            $this->components->twoColumnDetail('<fg=gray>Palette</>', '<fg=gray>Source · 500</>');

            foreach ($palettes as $palette) {
                $this->components->twoColumnDetail($palette['name'], $palette['source'].' · '.($palette['steps'][500] ?? reset($palette['steps'])));
            }
        }

        if ($type !== 'palettes') {
            $this->newLine();
            $this->components->info(sprintf('%d tokens, theme "%s" (light / dark)', count($tokens), $theme->name()));
            $this->table(['Token', 'Group', 'Light', 'Dark'], array_map(static fn (array $token): array => [
                $token['name'],
                $token['group'],
                $token['light'] === $token['resolved_light'] ? $token['light'] : $token['light'].' → '.$token['resolved_light'],
                $token['dark'] === $token['resolved_dark'] ? $token['dark'] : $token['dark'].' → '.$token['resolved_dark'],
            ], $tokens));
        }

        return self::SUCCESS;
    }
}
