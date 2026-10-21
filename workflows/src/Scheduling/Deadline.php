<?php

declare(strict_types=1);

namespace Gcego\Workflows\Scheduling;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Workflows\Contracts\AssignmentResolver;
use Gcego\Workflows\Definitions\WorkflowNode;
use Gcego\Workflows\Support\Duration;

/**
 * What happens when a state lasts too long (a State child):
 *
 *     new Deadline('7 days', then: 'expire')                       // run "expire" as the "deadlines" system actor
 *     new Deadline('48 hours', escalate: Supervisors::class)       // reassign its open tasks and notify
 *
 * Processed by gcego:workflows:process-deadlines (Laravel's scheduler), once.
 */
final class Deadline extends WorkflowNode
{
    public const string After = 'after';

    /** A transition to run (it must name the "deadlines" system actor). */
    public const string Then = 'then';

    /** An AssignmentResolver: open tasks of the state move to these people. */
    public const string Escalate = 'escalate';

    public function after(): string
    {
        return $this->text(self::After) ?? '1 day';
    }

    public function then(): ?string
    {
        return $this->text(self::Then);
    }

    /**
     * @return class-string<AssignmentResolver>|null
     */
    public function escalate(): ?string
    {
        $resolver = $this->get(self::Escalate);

        return is_string($resolver) && is_subclass_of($resolver, AssignmentResolver::class) ? $resolver : null;
    }

    protected static function namedArguments(): array
    {
        return ['after' => self::After, 'then' => self::Then, 'escalate' => self::Escalate];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('workflows.deadline')
            ->withoutProperties(self::Id, self::Name, self::Label, self::Description)
            ->withProperties(
                new PropertyDefinition(self::After, Type::string(), required: true, rule: Duration::valid(...), ruleDescription: 'a duration such as "48 hours" or "3 days"'),
                new PropertyDefinition(self::Then, Type::string(), rule: self::validName(...), ruleDescription: 'a transition name'),
                new PropertyDefinition(self::Escalate, Type::string(), rule: static fn (mixed $value): bool => self::implements($value, AssignmentResolver::class), ruleDescription: 'a class implementing '.AssignmentResolver::class),
            )
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::After];
    }
}
