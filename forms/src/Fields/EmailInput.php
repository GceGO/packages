<?php

declare(strict_types=1);

namespace Gcego\Forms\Fields;

/**
 * A TextInput of type email (validated with Laravel's "email" rule).
 */
class EmailInput extends TextInput
{
    protected static function defaultType(): string
    {
        return 'email';
    }

    protected static function defaultAutocomplete(): string
    {
        return 'email';
    }
}
