<?php

declare(strict_types=1);

namespace Gcego\Testing\Tests\Fixtures\Providers;

use Gcego\Registry\Contracts\DefinitionProvider;
use Gcego\Testing\Tests\Fixtures\Definitions;

final class DefinitionsProvider implements DefinitionProvider
{
    public function definitions(): iterable
    {
        return Definitions::all();
    }
}
