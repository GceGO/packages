<?php

declare(strict_types=1);

namespace Gcego\Forms\Fields;

/**
 * One choice among options, as a radio group (fieldset and legend).
 */
class Radio extends ChoiceField
{
    public function view(): string
    {
        return 'gcego-forms::fields.radio';
    }
}
