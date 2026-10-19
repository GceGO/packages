<?php

declare(strict_types=1);

namespace Gcego\Vault\Enums;

/**
 * How gcego:vault:sync treats a configured system role's permissions.
 */
enum SyncMode: string
{
    // Only when the role is created; afterwards administrators own its permissions.
    case Create = 'create';
    // Add configured permissions that are missing; never remove any.
    case Merge = 'merge';
    // Exactly the configured permissions (changes made in the screens are undone).
    case Replace = 'replace';
}
