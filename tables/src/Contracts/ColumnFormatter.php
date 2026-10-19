<?php

declare(strict_types=1);

namespace Gcego\Tables\Contracts;

use Gcego\Tables\Columns\Column;

/**
 * Custom cell text: new Format(GradeFormatter::class).
 *
 * The result is plain text and is always escaped; return null for "no value".
 */
interface ColumnFormatter
{
    public function format(mixed $value, mixed $record, Column $column): string|int|float|null;
}
