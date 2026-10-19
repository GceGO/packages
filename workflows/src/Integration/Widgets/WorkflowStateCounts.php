<?php

declare(strict_types=1);

namespace Gcego\Workflows\Integration\Widgets;

use Gcego\Widgets\Contracts\WidgetDataProvider;
use Gcego\Widgets\Data\WidgetDataContext;
use Gcego\Widgets\Data\WidgetDataResult;
use Gcego\Workflows\Instances\InstanceStatus;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Registry\WorkflowDefinitions;
use Gcego\Workflows\Support\Keys;
use Throwable;

/**
 * Running instances of one workflow per state, in the widget's tenant
 * (one grouped query). Aggregate counts only: protect the widget with the
 * requirements of who may see them.
 *
 *     final class RegistrationsByState extends WorkflowStateCounts
 *     {
 *         protected function workflow(): string { return 'institution-registration'; }
 *     }
 */
abstract class WorkflowStateCounts implements WidgetDataProvider
{
    abstract protected function workflow(): string;

    public function resolve(WidgetDataContext $context): WidgetDataResult
    {
        try {
            $workflow = app(WorkflowDefinitions::class)->resolve($this->workflow());
        } catch (Throwable) {
            return WidgetDataResult::empty();
        }

        $tenant = $context->tenant === null ? null : (string) $context->tenant->key;
        $counts = WorkflowInstance::query()->inTenant($tenant)->where('workflow', $workflow->workflowName())->whereIn('status', [InstanceStatus::Active->value, InstanceStatus::Suspended->value])
            ->selectRaw('state, count(*) as aggregate')->groupBy('state')->pluck('aggregate', 'state');

        $points = [];

        foreach ($workflow->states() as $state) {
            if (! $state->isTerminal()) {
                $points[$state->label()] = Keys::int($counts[$state->nodeName()] ?? 0);
            }
        }

        return WidgetDataResult::series($workflow->title(), $points);
    }
}
