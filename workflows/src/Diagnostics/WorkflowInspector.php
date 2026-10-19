<?php

declare(strict_types=1);

namespace Gcego\Workflows\Diagnostics;

use Gcego\Workflows\History\EventType;
use Gcego\Workflows\Instances\InstanceStatus;
use Gcego\Workflows\Models\WorkflowEvent;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Models\WorkflowTask;
use Gcego\Workflows\Registry\WorkflowDefinitions;
use Gcego\Workflows\Support\Keys;
use Gcego\Workflows\WorkflowsConfig;
use Throwable;

/**
 * What an operator needs to know (gcego:workflows:inspect): registered
 * workflows with their version, structure, validation problems and
 * unreachable states; instances per status; overdue tasks; due deadlines;
 * recent automation failures; instances whose version this code can no
 * longer continue; and the effective configuration. Counts and names
 * only — no subject data or comments.
 */
final readonly class WorkflowInspector
{
    public function __construct(private WorkflowDefinitions $definitions, private WorkflowsConfig $config) {}

    /**
     * @return array{workflows: list<array<string, mixed>>, runtime: array<string, mixed>, config: array<string, mixed>}
     */
    public function report(bool $runtime = true): array
    {
        $workflows = [];

        foreach ($this->definitions->names() as $name) {
            try {
                $workflow = $this->definitions->resolve($name);
                $workflows[] = [
                    'name' => $name,
                    'title' => $workflow->title(),
                    'version' => $workflow->version(),
                    'compatible' => $workflow->compatibleVersions(),
                    'structure' => $workflow->structureHash(),
                    'states' => array_map(static fn ($state): string => $state->nodeName().($state->isTerminal() ? ' (terminal)' : ''), $workflow->states()),
                    'transitions' => array_map(static fn ($transition): string => $transition->nodeName().': '.implode('|', $transition->from()).' -> '.$transition->to(), $workflow->transitions()),
                    'approvals' => array_map(static fn ($approval): string => $approval->nodeName().' @ '.$approval->state(), $workflow->approvals()),
                    'problems' => $workflow->problems(),
                    'unreachable' => $workflow->unreachableStates(),
                ];
            } catch (Throwable $exception) {
                $workflows[] = ['name' => $name, 'problems' => [$exception->getMessage()]];
            }
        }

        return ['workflows' => $workflows, 'runtime' => $runtime ? $this->runtime() : [], 'config' => [
            'optimistic_locking' => $this->config->optimisticLocking,
            'automation' => $this->config->automation,
            'queue' => $this->config->queue,
            'deadlines' => $this->config->deadlines,
            'schedule' => $this->config->schedule,
            'notifications' => $this->config->notifications,
            'enforce_tenant_scope' => $this->config->enforceTenantScope,
            'self_approval' => $this->config->selfApproval,
            'max_delegations' => $this->config->maxDelegations,
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private function runtime(): array
    {
        try {
            $incompatible = [];

            foreach (WorkflowInstance::query()->whereIn('status', [InstanceStatus::Active->value, InstanceStatus::Suspended->value])->selectRaw('workflow, version, count(*) as aggregate')->groupBy('workflow', 'version')->get() as $row) {
                try {
                    $this->definitions->forInstance((new WorkflowInstance)->forceFill(['workflow' => $row->workflow, 'version' => $row->version, 'state' => '']));
                } catch (Throwable) {
                    $incompatible[] = Keys::string($row->workflow).' v'.Keys::string($row->version).' ('.Keys::string($row->getAttribute('aggregate')).')';
                }
            }

            return [
                'instances' => WorkflowInstance::query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status')->all(),
                'open_tasks' => WorkflowTask::query()->open()->count(),
                'overdue_tasks' => WorkflowTask::query()->open()->whereNotNull('overdue_at')->count(),
                'due_deadlines' => WorkflowInstance::query()->where('status', InstanceStatus::Active->value)->whereNotNull('deadline_at')->where('deadline_at', '<=', now())->count(),
                'automation_failures_24h' => WorkflowEvent::query()->where('type', EventType::AutomationFailed)->where('created_at', '>=', now()->subDay())->count(),
                'incompatible_versions' => $incompatible,
            ];
        } catch (Throwable $exception) {
            return ['error' => 'Workflow tables are not available: '.class_basename($exception)];
        }
    }
}
