<?php

declare(strict_types=1);

namespace Gcego\Workflows\Tests\Fixtures\Handlers;

use Gcego\Workflows\Contracts\TransitionGuard;
use Gcego\Workflows\Engine\GuardResult;
use Gcego\Workflows\Instances\WorkflowContext;

final class DocumentsUploaded implements TransitionGuard
{
    public function check(WorkflowContext $context): GuardResult|bool
    {
        return $context->subject()?->getAttribute('documents') === true ? true : GuardResult::deny('Upload the registration documents first.');
    }
}
