<?php

declare(strict_types=1);

namespace Gcego\Workflows\Approvals;

enum Decision: string
{
    case Approve = 'approve';
    case Reject = 'reject';
    case Changes = 'changes';
    case Abstain = 'abstain';
}
