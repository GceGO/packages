<?php

declare(strict_types=1);

namespace Gcego\Components\Tests\Fixtures;

use Gcego\Components\Contracts\PlacementConfiguration;

final readonly class Placement implements PlacementConfiguration
{
    public function normalize(): string
    {
        return 'span-2';
    }
}
