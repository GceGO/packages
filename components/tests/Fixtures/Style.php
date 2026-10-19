<?php

declare(strict_types=1);

namespace Gcego\Components\Tests\Fixtures;

use Gcego\Components\Contracts\ConfigurationNode;

/**
 * A Tailwind-like styling modifier: configuration, not a renderable child.
 */
final readonly class Style implements ConfigurationNode
{
    public function __construct(public string $classes) {}

    /**
     * @return array{classes: string}
     */
    public function normalize(): array
    {
        return ['classes' => $this->classes];
    }
}
