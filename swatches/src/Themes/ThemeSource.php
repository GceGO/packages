<?php

declare(strict_types=1);

namespace Gcego\Swatches\Themes;

enum ThemeSource: string
{
    /** Shipped with gcego/swatches (also compiled in resources/css/themes.css). */
    case BuiltIn = 'built-in';

    /** From config/gcego-swatches.php. */
    case Config = 'config';

    /** Registered in code (a service provider). */
    case Runtime = 'runtime';
}
