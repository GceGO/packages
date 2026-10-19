<?php

declare(strict_types=1);

namespace Gcego\Components\Tests\Fixtures;

use Gcego\Components\Contracts\ConfigurationNode;

final readonly class OtherConfiguration implements ConfigurationNode
{
    public function normalize(): null
    {
        return null;
    }
}
