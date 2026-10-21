<?php

declare(strict_types=1);

namespace Gcego\Workflows\Approvals;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Workflows\Contracts\AssignmentResolver;
use Gcego\Workflows\Definitions\WorkflowNode;

/**
 * One step of a sequential approval (an Approval child). Stages run in
 * declaration order; each has its reviewers (an AssignmentResolver, or
 * anyone who satisfies its requirements) and its own policy.
 *
 *     new Stage('department', 'Department review', reviewers: DepartmentHeads::class)
 *     new Stage('academic', 'Academic review', new Ability('applications.review'), policy: 'quorum', requiredApprovals: 2)
 */
final class Stage extends WorkflowNode
{
    public const string Reviewers = 'reviewers';

    /** any, all, quorum or majority. */
    public const string Policy = 'policy';

    public const string RequiredApprovals = 'required_approvals';

    /**
     * @return class-string<AssignmentResolver>|null
     */
    public function reviewers(): ?string
    {
        $resolver = $this->get(self::Reviewers);

        return is_string($resolver) && is_subclass_of($resolver, AssignmentResolver::class) ? $resolver : null;
    }

    public function policy(): ApprovalPolicy
    {
        return ApprovalPolicy::tryFrom($this->text(self::Policy) ?? '') ?? (($this->integer(self::RequiredApprovals) ?? 1) > 1 ? ApprovalPolicy::Quorum : ApprovalPolicy::Any);
    }

    public function requiredApprovals(): int
    {
        return $this->integer(self::RequiredApprovals) ?? 1;
    }

    protected static function namedArguments(): array
    {
        return [...parent::namedArguments(), 'reviewers' => self::Reviewers, 'policy' => self::Policy, 'requiredApprovals' => self::RequiredApprovals];
    }

    /**
     * @return list<PropertyDefinition>
     */
    public static function reviewProperties(): array
    {
        return [
            new PropertyDefinition(self::Reviewers, Type::string(), rule: static fn (mixed $value): bool => self::implements($value, AssignmentResolver::class), ruleDescription: 'a class implementing '.AssignmentResolver::class),
            new PropertyDefinition(self::Policy, Type::string(), allowedValues: ApprovalPolicy::values()),
            new PropertyDefinition(self::RequiredApprovals, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= 50, ruleDescription: 'between 1 and 50'),
        ];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('workflows.stage')
            ->withoutProperties(self::Id)
            ->withProperties(new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validName(...), ruleDescription: 'a lowercase name'), ...self::reviewProperties())
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
