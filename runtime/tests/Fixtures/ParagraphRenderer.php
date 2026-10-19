<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Runtime\Blade\BladeRenderer;

final class ParagraphRenderer extends BladeRenderer
{
    protected function view(): string
    {
        return 'fixtures::paragraph';
    }
}
