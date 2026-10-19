<?php

declare(strict_types=1);

namespace Gcego\Media\Definitions;

enum MediaVisibility: string
{
    /** Served only through authorized media routes. */
    case Private = 'private';

    /** Intentionally public assets (the disk's public URL). */
    case Public = 'public';
}
