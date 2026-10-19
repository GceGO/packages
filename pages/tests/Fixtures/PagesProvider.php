<?php

declare(strict_types=1);

namespace Gcego\Pages\Tests\Fixtures;

use Gcego\Registry\Contracts\DefinitionProvider;

/**
 * Registers the test pages through gcego-registry.providers, so they are in
 * the registry index when the application boots (and their routes are
 * registered then, from the index).
 */
final class PagesProvider implements DefinitionProvider
{
    public function definitions(): iterable
    {
        return Definitions::all();
    }
}
