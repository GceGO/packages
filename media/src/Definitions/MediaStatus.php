<?php

declare(strict_types=1);

namespace Gcego\Media\Definitions;

/**
 * Where a media record stands. Only "ready" media is served; quarantined
 * media (failed or pending malware scan) never is.
 */
enum MediaStatus: string
{
    /** Stored, waiting for processing (metadata, variants, scan). */
    case Pending = 'pending';

    case Processing = 'processing';

    case Ready = 'ready';

    /** Processing failed; the original is kept and may still be served. */
    case Failed = 'failed';

    /** The malware scanner flagged it (or could not scan it): never served. */
    case Quarantined = 'quarantined';

    public function servable(): bool
    {
        return $this !== self::Quarantined;
    }
}
