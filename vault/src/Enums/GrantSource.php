<?php

declare(strict_types=1);

namespace Gcego\Vault\Enums;

/**
 * Where an assignment or grant came from (its provenance).
 */
enum GrantSource: string
{
    // An administrator through the governed API or the Vault screens.
    case Manual = 'manual';
    // Trusted application code (seeders, console, the model API).
    case System = 'system';
    // An approved access request.
    case Request = 'request';
    // A role import.
    case Import = 'import';
    // gcego:vault:sync or a role-column bridge.
    case Sync = 'sync';
}
