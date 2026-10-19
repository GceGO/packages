<?php

declare(strict_types=1);

namespace Gcego\Workflows\Tests\Fixtures\Handlers;

use Gcego\Security\Tenancy\TenantContext;
use Gcego\Workflows\Instances\WorkflowContext;

/**
 * What fixture handlers saw (reset per test).
 */
final class Recorder
{
    /** @var list<array{handler: string, instance: string, state: string, tenant: string|null, actor: string, current_tenant: string|null}> */
    public static array $calls = [];

    /** Remaining failures of FlakyHandler. */
    public static int $failures = 0;

    public static function record(string $handler, WorkflowContext $context): void
    {
        $tenant = app(TenantContext::class)->tenant();

        self::$calls[] = [
            'handler' => $handler,
            'instance' => $context->instance->ulid,
            'state' => $context->state(),
            'tenant' => $context->tenant(),
            'actor' => $context->isSystem() ? 'system' : 'user',
            'current_tenant' => $tenant === null ? null : (string) $tenant->key,
        ];
    }
}
