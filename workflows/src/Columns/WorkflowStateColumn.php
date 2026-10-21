<?php

declare(strict_types=1);

namespace Gcego\Workflows\Columns;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Tables\Columns\Column;
use Gcego\Tables\TablesConfig;
use Gcego\Workflows\Definitions\WorkflowNode;
use Gcego\Workflows\Support\SubjectState;
use Gcego\Workflows\Support\WorkflowLabels;

/**
 * A gcego/tables badge with the record's workflow state (label and color
 * from the definition; only instances the viewer may see):
 *
 *     new WorkflowStateColumn('registration_state', 'Registration', new Property(WorkflowStateColumn::Workflow, 'institution-registration'))
 *
 * With Eager (subject models using HasWorkflows) instances are eager loaded, not queried per row.
 */
class WorkflowStateColumn extends Column
{
    public const string Workflow = 'workflow';

    /** Eager load the HasWorkflows relation (the subject model must use it). */
    public const string Eager = 'eager';

    public function cellView(): string
    {
        return 'gcego-workflows::columns.state';
    }

    public function cellData(mixed $value, mixed $record, TablesConfig $config): array
    {
        $workflow = is_string($name = $this->get(self::Workflow)) ? $name : '';
        $instance = app(SubjectState::class)->latest($record, $workflow);
        $labels = app(WorkflowLabels::class);

        return [
            'text' => $instance === null ? null : $labels->state($workflow, $instance->state),
            'color' => $instance === null ? 'neutral' : ($instance->isRunning() ? $labels->color($workflow, $instance->state) : ($instance->status->value === 'cancelled' ? 'neutral' : 'success')),
            'status' => $instance?->status->value,
        ];
    }

    public function exportValue(mixed $value, mixed $record, TablesConfig $config): string|int|float|null
    {
        $text = $this->cellData($value, $record, $config)['text'];

        return is_string($text) ? $text : null;
    }

    public function relations(): array
    {
        return $this->get(self::Eager) === true ? ['workflowInstances'] : [];
    }

    public function isRelationPath(): bool
    {
        return false;
    }

    public function isSearchable(): bool
    {
        return false;
    }

    public function isSortable(): bool
    {
        return false;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Workflow, Type::string(), required: true, rule: WorkflowNode::validName(...), ruleDescription: 'a workflow name'),
            new PropertyDefinition(self::Eager, Type::bool(), default: false),
        );
    }
}
