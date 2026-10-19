<?php

declare(strict_types=1);

namespace Gcego\Actions\Configuration;

use Closure;
use Gcego\Components\Contracts\ConfigurationNode;

/**
 * Trusted lifecycle hooks, in this order:
 *
 *     prepare        before resolution and authorization; receives the ResolvedAction only (read-only)
 *     beforeExecute  after authorization, confirmation and validation, inside the transaction
 *     afterSuccess   after the transaction committed
 *     afterFailure   after the transaction rolled back (or the run was refused)
 *     afterComplete  always, last
 *
 * Mutations belong in beforeExecute or the handler: nothing before
 * authorization receives the record, so a hook cannot act before access is checked.
 * Exceptions from afterSuccess, afterFailure and afterComplete are reported, not rethrown.
 */
final readonly class Lifecycle implements ConfigurationNode
{
    public function __construct(
        public ?Closure $prepare = null,
        public ?Closure $beforeExecute = null,
        public ?Closure $afterSuccess = null,
        public ?Closure $afterFailure = null,
        public ?Closure $afterComplete = null,
    ) {}

    /**
     * @return array{hooks: list<string>}
     */
    public function normalize(): array
    {
        return ['hooks' => array_keys(array_filter(['prepare' => $this->prepare, 'beforeExecute' => $this->beforeExecute, 'afterSuccess' => $this->afterSuccess, 'afterFailure' => $this->afterFailure, 'afterComplete' => $this->afterComplete]))];
    }
}
