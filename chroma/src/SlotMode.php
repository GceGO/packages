<?php

declare(strict_types=1);

namespace Gcego\Chroma;

/**
 * How a Slot's classes combine with the classes accumulated so far.
 */
enum SlotMode: string
{
    /**
     * Add classes; a class replaces an earlier class of the same utility group (see ClassMerger).
     */
    case Merge = 'merge';

    /**
     * Discard everything accumulated so far and use exactly these classes.
     */
    case Replace = 'replace';

    /**
     * Remove these exact classes.
     */
    case Remove = 'remove';
}
