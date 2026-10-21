<?php

declare(strict_types=1);

namespace Gcego\Workflows\Tasks;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Workflows\Contracts\AssignmentResolver;
use Gcego\Workflows\Definitions\WorkflowNode;
use Gcego\Workflows\Support\Duration;

/**
 * Human work created when its state is entered (a State child):
 *
 *     new Task('verify-documents', 'Verify the documents', assign: Registrars::class, due: '2 days', completes: 'verify')
 *     new Task('check', 'Check the request', new Ability('requests.review'))   // a pool task: anyone allowed may take it
 *
 * Assignees come from the AssignmentResolver (each must satisfy the task's
 * requirements and belong to the tenant); without one, the task is open to
 * everyone who satisfies its gcego/security requirements. Completing it
 * runs Completes (when set) as the "tasks" system actor.
 */
final class Task extends WorkflowNode
{
    public const string Assign = 'assign';

    public const string Due = 'due';

    /** low, normal, high or urgent. */
    public const string Priority = 'priority';

    public const string Completes = 'completes';

    public const array Priorities = ['low', 'normal', 'high', 'urgent'];

    /**
     * @return class-string<AssignmentResolver>|null
     */
    public function assign(): ?string
    {
        $resolver = $this->get(self::Assign);

        return is_string($resolver) && is_subclass_of($resolver, AssignmentResolver::class) ? $resolver : null;
    }

    public function due(): ?string
    {
        return $this->text(self::Due);
    }

    public function priority(): string
    {
        return $this->text(self::Priority) ?? 'normal';
    }

    public function completes(): ?string
    {
        return $this->text(self::Completes);
    }

    protected static function namedArguments(): array
    {
        return [...parent::namedArguments(), 'assign' => self::Assign, 'due' => self::Due, 'priority' => self::Priority, 'completes' => self::Completes];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('workflows.task')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validName(...), ruleDescription: 'a lowercase name'),
                new PropertyDefinition(self::Assign, Type::string(), rule: static fn (mixed $value): bool => self::implements($value, AssignmentResolver::class), ruleDescription: 'a class implementing '.AssignmentResolver::class),
                new PropertyDefinition(self::Due, Type::string(), rule: Duration::valid(...), ruleDescription: 'a duration such as "2 days"'),
                new PropertyDefinition(self::Priority, Type::string(), default: 'normal', allowedValues: self::Priorities),
                new PropertyDefinition(self::Completes, Type::string(), rule: self::validName(...), ruleDescription: 'a transition name'),
            )
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
