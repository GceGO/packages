<?php

declare(strict_types=1);

namespace Gcego\Media\Definitions;

/**
 * The malware scan of a file. "Unscanned" is not "clean": without a
 * configured scanner nothing is claimed about the file. With a scanner,
 * media is served only once it is "clean" (or "error" when the failure
 * policy allows it).
 */
enum ScanStatus: string
{
    case Unscanned = 'unscanned';
    case Pending = 'pending';
    case Clean = 'clean';
    case Infected = 'infected';
    case Error = 'error';
}
