<?php

declare(strict_types=1);

namespace Gcego\Chroma\Tests\Fixtures;

/**
 * Shows why classes must be written in full: the concatenated one cannot be found by Tailwind.
 */
final class DynamicClasses
{
    public const string Literal = 'grid-cols-7';

    public static function concatenated(int $columns): string
    {
        return 'grid-cols-'.$columns;
    }
}
