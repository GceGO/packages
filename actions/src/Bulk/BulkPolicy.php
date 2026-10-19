<?php

declare(strict_types=1);

namespace Gcego\Actions\Bulk;

/**
 * What happens when some selected records cannot be processed.
 *
 * AllOrNothing (default): any missing, out-of-scope or unauthorized record
 * stops the action before anything runs, and the whole run is one transaction.
 * Partial: allowed records are processed (each in its own transaction); the
 * others are reported, never processed.
 */
enum BulkPolicy: string
{
    case AllOrNothing = 'all-or-nothing';
    case Partial = 'partial';
}
