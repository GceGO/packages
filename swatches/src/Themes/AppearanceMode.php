<?php

declare(strict_types=1);

namespace Gcego\Swatches\Themes;

enum AppearanceMode: string
{
    case Light = 'light';
    case Dark = 'dark';

    /**
     * Follow the operating system's prefers-color-scheme.
     */
    case System = 'system';
}
