<?php

declare(strict_types=1);

namespace Gcego\Actions\Execution;

/**
 * Dispatched after every execution (after commit for successful ones): the
 * extension point for gcego/notifications, audit trails and UI refreshes.
 * It carries the safe result only.
 */
final readonly class ActionCompleted
{
    public function __construct(public ActionResult $result, public string|int|null $userKey) {}
}
