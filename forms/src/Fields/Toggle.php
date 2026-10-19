<?php

declare(strict_types=1);

namespace Gcego\Forms\Fields;

/**
 * A boolean switch: a native checkbox with role="switch".
 */
class Toggle extends Checkbox
{
    public function view(): string
    {
        return 'gcego-forms::fields.toggle';
    }
}
