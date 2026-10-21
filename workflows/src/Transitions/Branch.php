<?php

declare(strict_types=1);

namespace Gcego\Workflows\Transitions;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Workflows\Contracts\Condition;
use Gcego\Workflows\Definitions\WorkflowNode;

/**
 * A conditional destination of a transition: the first branch whose
 * Condition passes decides where the transition goes; otherwise its To.
 *
 *     new Transition('review', 'submitted', 'under-review', new Branch('returned', IncompleteSubmission::class))
 */
final class Branch extends WorkflowNode
{
    public const string To = 'to';

    public const string Condition = 'condition';

    public function to(): string
    {
        return $this->text(self::To) ?? '';
    }

    /**
     * @return class-string<Condition>
     */
    public function condition(): string
    {
        $condition = $this->get(self::Condition);

        return is_string($condition) && is_subclass_of($condition, Condition::class) ? $condition : throw new \LogicException('Invalid branch condition.');
    }

    protected static function namedArguments(): array
    {
        return ['to' => self::To, 'condition' => self::Condition];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('workflows.branch')
            ->withoutProperties(self::Id, self::Name, self::Label, self::Description)
            ->withProperties(
                new PropertyDefinition(self::To, Type::string(), required: true, rule: self::validName(...), ruleDescription: 'a state name'),
                new PropertyDefinition(self::Condition, Type::string(), required: true, rule: static fn (mixed $value): bool => self::implements($value, Condition::class), ruleDescription: 'a class implementing '.Condition::class),
            )
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::To, self::Condition];
    }
}
