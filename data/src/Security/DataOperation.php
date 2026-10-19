<?php

declare(strict_types=1);

namespace Gcego\Data\Security;

/**
 * Read operations that are authorized. Values are the Laravel policy abilities used for Eloquent sources.
 */
enum DataOperation: string
{
    /** Listing, filtering, searching, sorting and paginating records. */
    case ViewAny = 'viewAny';

    /** Resolving one record by key. */
    case View = 'view';
}
