<?php

declare(strict_types=1);

namespace Gcego\Panels\Switching;

/**
 * A panel the user may switch to.
 */
final readonly class PanelLink
{
    public function __construct(
        public string $name,
        public string $title,
        public ?string $description,
        public string $url,
        public bool $current,
    ) {}
}
