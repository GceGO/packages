<?php

declare(strict_types=1);

namespace Gcego\Settings\Approvals;

use Gcego\Workflows\Contracts\WorkflowHandler;
use Gcego\Workflows\Instances\WorkflowContext;

/**
 * Automation of the approving transition of a settings approval workflow:
 *
 *     new Transition('approve', 'pending', 'approved', new Automation('apply', ApplySettingRequest::class, queue: false), ability: 'approve')
 *
 * Applies the request (validated and checked for conflicts again). Idempotent:
 * a request that is no longer pending is left alone.
 */
final readonly class ApplySettingRequest implements WorkflowHandler
{
    public function __construct(private SettingApprovals $approvals) {}

    public function handle(WorkflowContext $context): ?string
    {
        $subject = $context->subject();

        if ($subject instanceof SettingRequest) {
            $this->approvals->apply($subject);
        }

        return null;
    }
}
