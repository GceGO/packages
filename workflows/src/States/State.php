<?php

declare(strict_types=1);

namespace Gcego\Workflows\States;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Luminaries\IconName;
use Gcego\Workflows\Automation\Automation;
use Gcego\Workflows\Definitions\WorkflowNode;
use Gcego\Workflows\Scheduling\Deadline;
use Gcego\Workflows\Tasks\Task;

/**
 * A workflow state:
 *
 *     new State('draft')
 *     new State(name: 'approved', label: 'Approved', terminal: true, color: 'success')
 *     new State(new Property(State::Name, 'draft'), new Property(State::Label, 'Draft'))
 *
 * Children: Task (human work created on entering), Deadline (what happens
 * when the state lasts too long), Automation (run on entering).
 * Presentation (label, icon, color) never grants anything.
 */
final class State extends WorkflowNode
{
    public const string Icon = 'icon';

    /** neutral, info, success, warning or danger. */
    public const string Color = 'color';

    /** No transition leaves it, except ones marked Transition::Reopen. */
    public const string Terminal = 'terminal';

    public const string Metadata = 'metadata';

    public const array Colors = ['neutral', 'info', 'success', 'warning', 'danger'];

    public function isTerminal(): bool
    {
        return $this->get(self::Terminal) === true;
    }

    public function color(): string
    {
        return $this->text(self::Color) ?? ($this->isTerminal() ? 'success' : 'info');
    }

    public function icon(): ?string
    {
        return $this->text(self::Icon);
    }

    /**
     * @return list<Task>
     */
    public function tasks(): array
    {
        return $this->childrenOf(Task::class);
    }

    public function deadline(): ?Deadline
    {
        return $this->childrenOf(Deadline::class)[0] ?? null;
    }

    /**
     * @return list<Automation>
     */
    public function automations(): array
    {
        return $this->childrenOf(Automation::class);
    }

    protected static function namedArguments(): array
    {
        return [...parent::namedArguments(), 'terminal' => self::Terminal, 'icon' => self::Icon, 'color' => self::Color, 'metadata' => self::Metadata];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('workflows.state')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validName(...), ruleDescription: 'a lowercase name such as "under-review"'),
                new PropertyDefinition(self::Icon, Type::string(), rule: IconName::accepts(...), ruleDescription: 'an icon name'),
                new PropertyDefinition(self::Color, Type::string(), allowedValues: self::Colors),
                new PropertyDefinition(self::Terminal, Type::bool(), default: false),
                new PropertyDefinition(self::Metadata, Type::array(), rule: static fn (mixed $value): bool => is_array($value) && array_filter($value, static fn (mixed $entry): bool => ! is_scalar($entry) && $entry !== null) === [], ruleDescription: 'scalar values'),
            )
            ->withAllowedChildren([Task::class, Deadline::class, Automation::class]);
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
