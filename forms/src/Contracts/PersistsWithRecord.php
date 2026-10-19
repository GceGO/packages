<?php

declare(strict_types=1);

namespace Gcego\Forms\Contracts;

use Gcego\Forms\FormsConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * A field whose value is not a model attribute (gcego/media uploads and
 * attachments). Its validated value is staged instead of written to the
 * record, and persisted once the record is saved: through gcego/actions,
 * inside the action's transaction (ValidatedInput::$afterSave). Standalone
 * forms (FormSubmitted) receive only attribute data.
 *
 * Top-level fields only (not inside repeaters).
 */
interface PersistsWithRecord
{
    /**
     * Check the validated value and prepare what persist() needs (no writes yet).
     *
     * @throws ValidationException
     */
    public function stage(mixed $value, ?Model $record, FormsConfig $config): mixed;

    /**
     * Write the staged value for the saved record.
     */
    public function persist(Model $record, mixed $staged): void;
}
