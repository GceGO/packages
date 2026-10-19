<?php

declare(strict_types=1);

namespace Gcego\Workflows\Support;

use Gcego\Workflows\Actors\SystemActor;
use Gcego\Workflows\Models\WorkflowEvent;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Models\WorkflowTask;
use Gcego\Workflows\Registry\WorkflowDefinitions;
use Gcego\Workflows\Workflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;
use Throwable;

/**
 * Display labels from definitions (escaped by Blade), with safe fallbacks
 * when a definition changed or disappeared.
 */
final class WorkflowLabels
{
    /** @var array<string, Workflow|null> */
    private array $workflows = [];

    public function __construct(private readonly WorkflowDefinitions $definitions) {}

    public function workflow(string $name): ?Workflow
    {
        if (! array_key_exists($name, $this->workflows)) {
            try {
                $this->workflows[$name] = $this->definitions->resolve($name);
            } catch (Throwable) {
                $this->workflows[$name] = null;
            }
        }

        return $this->workflows[$name];
    }

    public function title(WorkflowInstance $instance): string
    {
        return $this->workflow($instance->workflow)?->title() ?? $instance->workflow;
    }

    public function state(string $workflow, ?string $state): string
    {
        return $state === null ? '' : ($this->workflow($workflow)?->state($state)?->label() ?? $state);
    }

    public function color(string $workflow, string $state): string
    {
        return $this->workflow($workflow)?->state($state)?->color() ?? 'neutral';
    }

    public function task(WorkflowTask $task): string
    {
        $workflow = $this->workflow($task->instance->workflow);

        if ($task->isApproval()) {
            $approval = $workflow?->approval($task->name);
            $stage = $approval?->stages()[$task->stage_index] ?? null;
            $label = $approval?->label() ?? $task->name;

            return $approval !== null && count($approval->stages()) > 1 && $stage !== null ? $label.' — '.$stage->label() : $label;
        }

        foreach ($workflow?->state($task->state)?->tasks() ?? [] as $definition) {
            if ($definition->nodeName() === $task->name) {
                return $definition->label();
            }
        }

        return $task->name;
    }

    public function transition(string $workflow, ?string $name): string
    {
        foreach ($this->workflow($workflow)?->transitions() ?? [] as $transition) {
            if ($transition->nodeName() === $name) {
                return $transition->label();
            }
        }

        return (string) $name;
    }

    public function actor(WorkflowEvent $event): string
    {
        if ($event->actor_kind === 'system') {
            $key = 'gcego-workflows::messages.actors.'.($event->actor_name ?? 'system');

            return (string) __('gcego-workflows::messages.system', ['name' => Lang::has($key) ? __($key) : ($event->actor_name ?? 'system')]);
        }

        $actor = $event->actor;

        return $actor instanceof Model ? self::name($actor) : (string) __('gcego-workflows::messages.someone');
    }

    public static function name(Model $person): string
    {
        $name = $person->getAttribute('name');

        return is_string($name) && $name !== '' ? $name : (string) __('gcego-workflows::messages.someone');
    }

    public function event(WorkflowEvent $event, string $workflow): string
    {
        $metadata = $event->metadata ?? [];
        $string = static fn (string $key): string => is_scalar($metadata[$key] ?? null) ? (string) $metadata[$key] : '';
        $approval = $this->workflow($workflow)?->approval($string('approval'))?->label() ?? $string('approval');

        return (string) __('gcego-workflows::messages.events.'.$event->type, [
            'from' => $this->state($workflow, $event->from_state),
            'to' => $this->state($workflow, $event->to_state),
            'transition' => $this->transition($workflow, $string('transition')),
            'approval' => $approval,
            'decision' => $string('decision') === '' ? '' : __('gcego-workflows::messages.decisions.'.$string('decision')),
            'outcome' => $string('outcome') === '' ? '' : __('gcego-workflows::messages.outcomes.'.$string('outcome')),
            'name' => $string('name') !== '' ? $string('name') : $string('automation'),
        ]);
    }

    public static function systemName(SystemActor $actor): string
    {
        return (string) __('gcego-workflows::messages.actors.'.$actor->name);
    }
}
