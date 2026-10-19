<?php

declare(strict_types=1);

namespace Gcego\Security\Fields;

/**
 * What a user may do with one field, from least to most.
 */
enum FieldAccess: int
{
    case Hidden = 0;
    case Redacted = 1;
    case Readable = 2;
    case Editable = 3;

    public function canRead(): bool
    {
        return $this->value >= self::Readable->value;
    }

    public function canWrite(): bool
    {
        return $this === self::Editable;
    }
}
