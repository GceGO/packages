<?php

declare(strict_types=1);

namespace Gcego\Panels\Assets;

use Gcego\Pages\PagesConfig;
use Gcego\Panels\Panel;
use Gcego\Panels\PanelsConfig;

/**
 * The Vite entries of a panel's documents: Panel::Vite, else
 * gcego-panels.assets.vite, else gcego-pages.rendering.vite. Entries are
 * trusted configuration (validated paths); the application's Vite build
 * must contain them (its CSS imports gcego/chroma's gcego.css).
 */
final readonly class PanelAssetResolver
{
    public function __construct(private PanelsConfig $config, private PagesConfig $pages) {}

    /**
     * @return list<string>
     */
    public function vite(Panel $panel): array
    {
        return $panel->vite() ?? $this->config->vite ?? $this->pages->vite;
    }
}
