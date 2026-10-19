<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests\Fixtures;

use Gcego\Components\Contracts\ConfigurationNode;

/**
 * A Tailwind-like styling node: configuration, never a part or child.
 */
final readonly class ExampleStyle implements ConfigurationNode
{
    /**
     * @param  list<string>  $classes
     */
    public function __construct(public array $classes) {}

    /**
     * @return array{classes: list<string>}
     */
    public function normalize(): array
    {
        return ['classes' => $this->classes];
    }
}
