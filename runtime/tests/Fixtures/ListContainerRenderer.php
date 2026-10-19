<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Runtime\Blade\BladeRenderer;

final class ListContainerRenderer extends BladeRenderer
{
    protected function view(): string
    {
        return 'fixtures::container';
    }
}
