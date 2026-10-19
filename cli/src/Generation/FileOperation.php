<?php

declare(strict_types=1);

namespace Gcego\Cli\Generation;

enum FileOperation: string
{
    /** The file does not exist: it will be created. */
    case Create = 'create';

    /** The file exists with exactly this content: nothing to do. */
    case Unchanged = 'unchanged';

    /** The file exists and differs: kept, and the generation is refused (no --force). */
    case Conflict = 'conflict';

    /** The file exists and differs: replaced, because --force was given. */
    case Overwrite = 'overwrite';

    public function writes(): bool
    {
        return $this === self::Create || $this === self::Overwrite;
    }
}
