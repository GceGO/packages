<?php

declare(strict_types=1);

namespace Gcego\Components\Examples\Configurations;

use Gcego\Components\Contracts\ConfigurationNode;
use InvalidArgumentException;

final readonly class Padding implements ConfigurationNode
{
    public function __construct(public int $pixels)
    {
        if ($pixels < 0) {
            throw new InvalidArgumentException('Padding cannot be negative.');
        }
    }

    /** @return array{padding: int} */
    public function normalize(): array
    {
        return ['padding' => $this->pixels];
    }
}
