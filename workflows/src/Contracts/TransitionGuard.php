<?php

declare(strict_types=1);

namespace Gcego\Workflows\Contracts;

use Gcego\Workflows\Engine\GuardResult;
use Gcego\Workflows\Instances\WorkflowContext;

/**
 * Decides whether a transition may happen now (documents uploaded, a
 * reviewer assigned, a deadline not passed...). Named by trusted
 * definitions only; resolved from the container. Return true, false, or a
 * GuardResult with a user-facing reason:
 *
 *     final class DocumentsUploaded implements TransitionGuard
 *     {
 *         public function check(WorkflowContext $context): GuardResult|bool
 *         {
 *             return $context->subject()?->documents()->exists() ? true : GuardResult::deny('Upload the required documents first.');
 *         }
 *     }
 *
 * Guards run inside the transition's transaction, after authorization.
 */
interface TransitionGuard
{
    public function check(WorkflowContext $context): GuardResult|bool;
}
