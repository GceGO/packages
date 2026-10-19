<?php

declare(strict_types=1);

namespace Gcego\Workflows\History;

/**
 * History event types (stable strings stored in gcego_workflow_events.type).
 */
final class EventType
{
    public const string Started = 'workflow.started';

    public const string Transitioned = 'workflow.transitioned';

    public const string Completed = 'workflow.completed';

    public const string Suspended = 'workflow.suspended';

    public const string Resumed = 'workflow.resumed';

    public const string Cancelled = 'workflow.cancelled';

    public const string Reopened = 'workflow.reopened';

    public const string ApprovalOpened = 'approval.opened';

    public const string ApprovalStageCompleted = 'approval.stage_completed';

    public const string ApprovalCompleted = 'approval.completed';

    public const string Decided = 'approval.decided';

    public const string TaskCreated = 'task.created';

    public const string TaskClaimed = 'task.claimed';

    public const string TaskCompleted = 'task.completed';

    public const string TaskDelegated = 'task.delegated';

    public const string TaskReassigned = 'task.reassigned';

    public const string TaskOverdue = 'task.overdue';

    public const string TaskEscalated = 'task.escalated';

    public const string DeadlineReached = 'deadline.reached';

    public const string AutomationCompleted = 'automation.completed';

    public const string AutomationFailed = 'automation.failed';
}
