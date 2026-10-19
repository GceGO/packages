<?php

declare(strict_types=1);

namespace Gcego\Actions;

/**
 * The semantic emphasis of an action; it selects the default button preset.
 */
enum ActionIntent: string
{
    case Primary = 'primary';
    case Secondary = 'secondary';
    case Destructive = 'destructive';

    public function preset(): string
    {
        return 'control.button.'.$this->value;
    }
}
