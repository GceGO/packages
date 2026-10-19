<?php

declare(strict_types=1);

namespace Gcego\Components\Tests\Fixtures;

/**
 * Stands in for an application model referenced by class-string.
 */
final class FakeModel
{
    public static int $constructed = 0;

    public function __construct()
    {
        self::$constructed++;
    }
}
