<?php

declare(strict_types=1);

namespace Gcego\Studio\Assets;

use Gcego\Studio\Configuration\StudioConfiguration;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;

/**
 * Laravel Vite integration, checked without building or changing anything:
 *
 * - the panels' Vite entries (gcego-studio.assets.vite, else
 *   gcego-panels.assets.vite) must be in the production manifest, and the
 *   files it names must exist — unless the dev server runs (public/hot);
 * - the CSS entry should use Tailwind CSS v4 (@import "tailwindcss") and
 *   import gcego/chroma's stylesheet, so GCEGO classes are generated;
 * - the JS entry must not start a second Alpine: Livewire 4 ships and
 *   starts Alpine itself.
 *
 * Panels include the entries with Laravel's @vite (once per page).
 */
final readonly class StudioAssetManager
{
    public function __construct(private StudioConfiguration $config, private Repository $repository, private Application $app) {}

    /**
     * @return list<string>
     */
    public function entries(): array
    {
        if ($this->config->vite !== null) {
            return $this->config->vite;
        }

        $panels = $this->repository->get('gcego-panels.assets.vite');

        return is_array($panels) ? array_values(array_filter($panels, is_string(...))) : [];
    }

    public function inspect(): AssetReport
    {
        $entries = $this->entries();
        $manifestPath = $this->config->manifest ?? $this->app->publicPath('build/manifest.json');
        $devServer = is_file($this->app->publicPath('hot'));
        $manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : null;
        $manifest = is_array($manifest) ? $manifest : null;
        $missingEntries = [];
        $missingFiles = [];
        $problems = [];
        $warnings = [];

        if ($manifest !== null) {
            foreach ($entries as $entry) {
                $chunk = $manifest[$entry] ?? null;

                if (! is_array($chunk)) {
                    $missingEntries[] = $entry;

                    continue;
                }

                $file = is_string($chunk['file'] ?? null) ? $chunk['file'] : null;

                if ($file !== null && ! is_file(dirname($manifestPath).'/'.$file)) {
                    $missingFiles[] = $file;
                }
            }
        }

        if ($entries !== [] && $manifest === null && ! $devServer) {
            $problems[] = 'No Vite manifest was found; run "npm run build" (or "npm run dev" while developing).';
        }

        if ($missingEntries !== []) {
            $problems[] = sprintf('The Vite manifest lacks %s; add them to the "input" of vite.config.js and rebuild.', implode(', ', $missingEntries));
        }

        if ($missingFiles !== []) {
            $problems[] = sprintf('Built files are missing (%s); rebuild the assets.', implode(', ', $missingFiles));
        }

        $css = $this->read($this->config->cssEntry);
        $tailwind4 = $css !== null && preg_match('/@import\s+["\']tailwindcss["\']/', $css) === 1;
        $styling = $css !== null && preg_match('#@import\s+["\'][^"\']*chroma/resources/css/gcego\.css["\']#', $css) === 1;

        if ($css === null) {
            $warnings[] = 'The CSS entry (gcego-studio.assets.css) was not found.';
        } else {
            if (! $tailwind4) {
                $warnings[] = 'The CSS entry does not import Tailwind CSS v4 (@import "tailwindcss";).';
            }

            if (! $styling) {
                $warnings[] = 'The CSS entry does not import gcego/chroma; add @import "../../vendor/gcego/chroma/resources/css/gcego.css"; so GCEGO classes and tokens are generated.';
            }
        }

        $js = $this->read($this->config->jsEntry);
        $duplicateAlpine = $js !== null && preg_match('/import\s+Alpine\s+from\s+["\']alpinejs["\']|Alpine\.start\s*\(/', $js) === 1;

        if ($duplicateAlpine) {
            $warnings[] = 'The JS entry imports or starts Alpine; Livewire already ships and starts it. Import Alpine from Livewire\'s ESM build instead, or remove it.';
        }

        return new AssetReport($entries, $manifest !== null, $devServer, $missingEntries, $missingFiles, $tailwind4, $styling, $duplicateAlpine, $problems, $warnings);
    }

    private function read(?string $path): ?string
    {
        if ($path === null || ! is_file($path) || filesize($path) > 2_000_000) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }
}
