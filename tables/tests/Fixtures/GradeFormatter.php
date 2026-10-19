<?php

declare(strict_types=1);

namespace Gcego\Tables\Tests\Fixtures;

use Gcego\Tables\Columns\Column;
use Gcego\Tables\Contracts\ColumnFormatter;

final class GradeFormatter implements ColumnFormatter
{
    public function format(mixed $value, mixed $record, Column $column): ?string
    {
        return is_numeric($value) ? ((float) $value >= 50 ? 'Pass' : 'Fail') : null;
    }
}
