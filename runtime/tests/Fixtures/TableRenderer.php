<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Runtime\Blade\BladeRenderer;

final class TableRenderer extends BladeRenderer
{
    protected function view(): string
    {
        return 'fixtures::table';
    }
}
