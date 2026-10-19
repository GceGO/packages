<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Components\Contracts\ConfigurationNode;

/**
 * A Tailwind-like configuration node: must never be rendered.
 */
final readonly class ExampleStyle implements ConfigurationNode
{
    /**
     * @return array{classes: string}
     */
    public function normalize(): array
    {
        return ['classes' => 'NEVER-RENDERED'];
    }
}
