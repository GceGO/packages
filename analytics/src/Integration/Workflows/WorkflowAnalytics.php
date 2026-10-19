<?php

declare(strict_types=1);

namespace Gcego\Analytics\Integration\Workflows;

use Gcego\Analytics\Datasets\Dataset;
use Gcego\Analytics\Datasets\Field;
use Gcego\Analytics\Dimensions\Dimension;
use Gcego\Analytics\Metrics\Metric;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Requirements\Ability;

/**
 * Optional analytics over gcego/workflows (registered when
 * gcego-analytics.integrations.workflows is on and the package is installed):
 *
 * - datasets "workflow-instances" (by workflow, state, status; started_at)
 *   and "workflow-tasks" (by kind, name, status, priority; created_at);
 * - metrics workflows_started, workflows_completed, pending_approvals,
 *   escalated_tasks, workflow_tasks, escalation_rate (escalated / all tasks, %).
 *
 * Tenant-scoped by the workflow tables' tenant column; protected by the
 * workflow managers' ability (gcego-workflows.security.manage_ability).
 * Durations are not offered: they need per-row arithmetic, not an aggregate.
 */
final class WorkflowAnalytics
{
    public static function register(RegistryInterface $registry, ?Requirement $requirement = null): void
    {
        foreach (self::definitions($requirement) as $definition) {
            $registry->register($definition);
        }
    }

    /**
     * @return list<Dataset|Metric>
     */
    public static function definitions(?Requirement $requirement = null): array
    {
        $ability = config('gcego-workflows.security.manage_ability', 'gcego-workflows.manage');
        $requirement ??= new Ability(is_string($ability) ? $ability : 'gcego-workflows.manage');

        return [
            new Dataset('workflow-instances', $requirement,
                new Field('started_at', 'Started', type: 'datetime'),
                new Field('completed_at', 'Completed', type: 'datetime'),
                new Dimension('workflow', 'Workflow'),
                new Dimension('state', 'State'),
                new Dimension('status', 'Status', options: ['active' => 'Active', 'suspended' => 'Suspended', 'completed' => 'Completed', 'cancelled' => 'Cancelled']),
                provider: WorkflowInstancesProvider::class,
                dateColumn: 'started_at',
            ),
            new Dataset('workflow-tasks', $requirement,
                new Field('created_at', 'Created', type: 'datetime'),
                new Field('due_at', 'Due', type: 'datetime'),
                new Dimension('kind', 'Kind', options: ['approval' => 'Approval', 'task' => 'Task']),
                new Dimension('name', 'Step'),
                new Dimension('status', 'Status', options: ['open' => 'Open', 'in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'escalated' => 'Escalated', 'delegated' => 'Delegated']),
                new Dimension('priority', 'Priority', options: ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent']),
                provider: WorkflowTasksProvider::class,
                dateColumn: 'created_at',
            ),
            new Metric(name: 'workflows_started', label: 'Workflows started', dataset: 'workflow-instances'),
            new Metric(name: 'workflows_completed', label: 'Workflows completed', dataset: 'workflow-instances', filters: ['status' => 'completed']),
            new Metric(name: 'pending_approvals', label: 'Pending approvals', dataset: 'workflow-tasks', filters: ['kind' => 'approval', 'status' => ['open', 'in_progress']]),
            new Metric(name: 'escalated_tasks', label: 'Escalated tasks', dataset: 'workflow-tasks', filters: ['status' => 'escalated']),
            new Metric(name: 'workflow_tasks', label: 'Workflow tasks', dataset: 'workflow-tasks'),
            new Metric(name: 'escalation_rate', label: 'Escalation rate', aggregate: 'percentage', numerator: 'escalated_tasks', denominator: 'workflow_tasks', precision: 1),
        ];
    }
}
