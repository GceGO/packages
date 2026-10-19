<?php

declare(strict_types=1);

namespace Gcego\Settings\Approvals;

use Gcego\Workflows\Contracts\WorkflowHandler;
use Gcego\Workflows\Instances\WorkflowContext;

/**
 * Automation of the refusing transition of a settings approval workflow:
 *
 *     new Transition('refuse', 'pending', 'refused', new Automation('discard', DiscardSettingRequest::class, queue: false), ability: 'approve')
 *
 * The request is closed and its value erased. Idempotent.
 */
final readonly class DiscardSettingRequest implements WorkflowHandler
{
    public function __construct(private SettingApprovals $approvals) {}

    public function handle(WorkflowContext $context): ?string
    {
        $subject = $context->subject();

        if ($subject instanceof SettingRequest) {
            $this->approvals->refuse($subject);
        }

        return null;
    }
}
