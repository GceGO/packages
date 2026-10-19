<?php

declare(strict_types=1);

namespace Gcego\Media\Definitions;

enum MediaAbility: string
{
    case View = 'view';
    case Download = 'download';
    case Upload = 'upload';
    case Attach = 'attach';
    case Replace = 'replace';
    case Delete = 'delete';
    case Manage = 'manage';
}
