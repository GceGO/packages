<?php

declare(strict_types=1);

namespace Gcego\Infolists\Contracts;

/**
 * A trusted, application-defined way to compute an entry's value from the
 * record (computed attributes, summaries): new Resolve(StudentAverage::class).
 * It receives the already authorized record; it must not query outside it
 * without applying its own scopes.
 */
interface ValueResolver
{
    public function resolve(mixed $record): mixed;
}
