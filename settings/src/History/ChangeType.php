<?php

declare(strict_types=1);

namespace Gcego\Settings\History;

enum ChangeType: string
{
    case Updated = 'updated';
    case Reset = 'reset';
    case Rotated = 'rotated';
    case Imported = 'imported';
    case Requested = 'requested';
    case Approved = 'approved';
    case Refused = 'refused';

    public function label(): string
    {
        return (string) __('gcego-settings::messages.changes.'.$this->value);
    }
}
