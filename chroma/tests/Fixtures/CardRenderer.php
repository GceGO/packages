<?php

declare(strict_types=1);

namespace Gcego\Chroma\Tests\Fixtures;

use Gcego\Runtime\Blade\BladeRenderer;

final class CardRenderer extends BladeRenderer
{
    protected function view(): string
    {
        return 'fixtures::card';
    }
}
