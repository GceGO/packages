<?php

declare(strict_types=1);

namespace Gcego\Forms\Fields;

/**
 * Several choices among options, as a checkbox group (fieldset and legend). The value is a list.
 */
class CheckboxList extends ChoiceField
{
    public function view(): string
    {
        return 'gcego-forms::fields.checkbox-list';
    }

    public function isMultiple(): bool
    {
        return true;
    }
}
