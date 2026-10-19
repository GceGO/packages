<?php

declare(strict_types=1);

namespace Gcego\Infolists\Contracts;

use Gcego\Infolists\Entries\Entry;

/**
 * Custom entry text: new Format(GradeFormatter::class). The result is plain text, always escaped.
 */
interface EntryFormatter
{
    public function format(mixed $value, mixed $record, Entry $entry): string|int|float|null;
}
