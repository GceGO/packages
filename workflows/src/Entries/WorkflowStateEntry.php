<?php

declare(strict_types=1);

namespace Gcego\Workflows\Entries;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Infolists\Entries\Entry;
use Gcego\Infolists\InfolistsConfig;
use Gcego\Workflows\Definitions\WorkflowNode;
use Gcego\Workflows\Support\SubjectState;
use Gcego\Workflows\Support\WorkflowLabels;

/**
 * A gcego/infolists entry with the record's workflow state, when it was
 * entered and the instance status (read-only; instances the viewer may see):
 *
 *     new WorkflowStateEntry('registration', 'Registration', new Property(WorkflowStateEntry::Workflow, 'institution-registration'))
 */
class WorkflowStateEntry extends Entry
{
    public const string Workflow = 'workflow';

    public const string Eager = 'eager';

    public function view(): string
    {
        return 'gcego-workflows::entries.state';
    }

    public function display(mixed $value, mixed $record, InfolistsConfig $config): array
    {
        $workflow = is_string($name = $this->get(self::Workflow)) ? $name : '';
        $instance = app(SubjectState::class)->latest($record, $workflow);
        $labels = app(WorkflowLabels::class);

        return [
            'text' => $instance === null ? null : $labels->state($workflow, $instance->state),
            'color' => $instance === null ? 'neutral' : ($instance->isRunning() ? $labels->color($workflow, $instance->state) : ($instance->status->value === 'cancelled' ? 'neutral' : 'success')),
            'status' => $instance?->status->value,
            'since' => $instance?->state_entered_at?->toIso8601String(),
            'since_human' => $instance?->state_entered_at?->diffForHumans(),
        ];
    }

    public function relations(): array
    {
        return $this->get(self::Eager) === true ? ['workflowInstances'] : [];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Workflow, Type::string(), required: true, rule: WorkflowNode::validName(...), ruleDescription: 'a workflow name'),
            new PropertyDefinition(self::Eager, Type::bool(), default: false),
        );
    }
}
