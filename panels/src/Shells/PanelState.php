<?php

declare(strict_types=1);

namespace Gcego\Panels\Shells;

/**
 * Presentation state of a panel document (no secrets: it is rendered).
 * The collapsed sidebar is a per-browser preference kept in localStorage
 * under $storageKey; the server always renders the expanded sidebar.
 */
final readonly class PanelState
{
    public function __construct(
        public string $layout,
        public ?string $theme,
        public bool $collapsible,
        public string $storageKey,
    ) {}
}
