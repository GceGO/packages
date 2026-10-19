<?php

declare(strict_types=1);

namespace Gcego\Core\Bootstrap;

enum BootStatus: string
{
    case Booted = 'booted';

    /**
     * gcego.enabled is false; nothing was booted.
     */
    case Disabled = 'disabled';
}
