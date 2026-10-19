<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Runtime\Blade\BladeRenderer;

final class SearchRenderer extends BladeRenderer
{
    protected function view(): string
    {
        return 'fixtures::search';
    }
}
