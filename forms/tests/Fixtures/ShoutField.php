<?php

declare(strict_types=1);

namespace Gcego\Forms\Tests\Fixtures;

use Gcego\Forms\Contracts\PersistsWithRecord;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\FormsConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * A field that is not a column: it writes the uppercased value into "code"
 * after the record is saved (as gcego/media attaches uploads).
 */
final class ShoutField extends TextInput implements PersistsWithRecord
{
    public function currentValue(Model $record): mixed
    {
        return is_string($code = $record->getAttribute('code')) ? strtolower($code) : null;
    }

    public function stage(mixed $value, ?Model $record, FormsConfig $config): mixed
    {
        if ($value === 'forbidden') {
            throw ValidationException::withMessages([$this->fieldName() => 'Not this one.']);
        }

        return is_string($value) ? strtoupper($value) : null;
    }

    public function persist(Model $record, mixed $staged): void
    {
        if ($staged === 'EXPLODE') {
            throw new RuntimeException('Storage failed.');
        }

        $record->forceFill(['code' => $staged])->save();
    }
}
