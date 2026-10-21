<?php

declare(strict_types=1);

namespace Gcego\Workflows\Approvals;

use Gcego\Components\Properties\Property;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Workflows\Contracts\AssignmentResolver;
use Gcego\Workflows\Definitions\WorkflowNode;
use Gcego\Workflows\Support\Duration;

/**
 * An approval, opened when its state is entered:
 *
 *     new Approval(name: 'academic-review', label: 'Academic Review', state: 'under-review',
 *         requiredApprovals: 2, reviewers: AcademicReviewers::class,
 *         onApprove: 'approve', onReject: 'reject', onChanges: 'return')
 *
 *     new Approval('registration',                                    // sequential
 *         new Stage('department', reviewers: DepartmentHeads::class),
 *         new Stage('final', 'Final review', new Ability('registrations.approve')),
 *         state: 'submitted', onApprove: 'approve', onReject: 'reject',
 *     )
 *
 * (PHP requires named arguments after positional ones: children first.)
 *
 * Without Stage children it is one parallel stage defined by its own
 * reviewers, policy and RequiredApprovals. Reviewers come from an
 * AssignmentResolver (one task each) or, without one, anyone satisfying
 * the approval's requirements may decide (a pool). Each person decides
 * once; initiators may not decide on their own request unless SelfApproval.
 * Outcomes run OnApprove / OnReject / OnChanges as the "approvals" system actor.
 */
final class Approval extends WorkflowNode
{
    public const string State = 'state';

    public const string OnApprove = 'on_approve';

    public const string OnReject = 'on_reject';

    public const string OnChanges = 'on_changes';

    /** A duration after which reviewers' tasks are overdue (and escalated when Escalate is set). */
    public const string Due = 'due';

    public const string Escalate = 'escalate';

    public const string SelfApproval = 'self_approval';

    public const string Delegation = 'delegation';

    public const string Abstain = 'abstain';

    public function state(): string
    {
        return $this->text(self::State) ?? '';
    }

    /**
     * The stages, in order (a single implicit stage when none are declared).
     *
     * @return list<Stage>
     */
    public function stages(): array
    {
        $stages = $this->childrenOf(Stage::class);

        if ($stages !== []) {
            return $stages;
        }

        $properties = [new Property(Stage::Name, $this->nodeName())];

        foreach ([Stage::Reviewers, Stage::Policy, Stage::RequiredApprovals] as $key) {
            if ($this->get($key) !== null) {
                $properties[] = new Property($key, $this->get($key));
            }
        }

        return [new Stage(...$properties, ...$this->requirements())];
    }

    public function onApprove(): ?string
    {
        return $this->text(self::OnApprove);
    }

    public function onReject(): ?string
    {
        return $this->text(self::OnReject);
    }

    public function onChanges(): ?string
    {
        return $this->text(self::OnChanges);
    }

    public function due(): ?string
    {
        return $this->text(self::Due);
    }

    /**
     * @return class-string<AssignmentResolver>|null
     */
    public function escalate(): ?string
    {
        $resolver = $this->get(self::Escalate);

        return is_string($resolver) && is_subclass_of($resolver, AssignmentResolver::class) ? $resolver : null;
    }

    public function allowsSelfApproval(?bool $default = false): bool
    {
        $value = $this->get(self::SelfApproval);

        return is_bool($value) ? $value : (bool) $default;
    }

    public function allowsDelegation(): bool
    {
        return $this->get(self::Delegation) === true;
    }

    public function allowsAbstaining(): bool
    {
        return $this->get(self::Abstain) === true;
    }

    protected static function namedArguments(): array
    {
        return [...parent::namedArguments(), 'state' => self::State, 'reviewers' => Stage::Reviewers, 'policy' => Stage::Policy, 'requiredApprovals' => Stage::RequiredApprovals, 'onApprove' => self::OnApprove, 'onReject' => self::OnReject, 'onChanges' => self::OnChanges, 'due' => self::Due, 'escalate' => self::Escalate, 'selfApproval' => self::SelfApproval, 'delegation' => self::Delegation, 'abstain' => self::Abstain];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $transition = static fn (string $key): PropertyDefinition => new PropertyDefinition($key, Type::string(), rule: self::validName(...), ruleDescription: 'a transition name');

        return parent::defineSchema()
            ->withType('workflows.approval')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validName(...), ruleDescription: 'a lowercase name such as "academic-review"'),
                new PropertyDefinition(self::State, Type::string(), rule: self::validName(...), ruleDescription: 'the state it opens in'),
                ...Stage::reviewProperties(),
                ...[
                    $transition(self::OnApprove), $transition(self::OnReject), $transition(self::OnChanges),
                    new PropertyDefinition(self::Due, Type::string(), rule: Duration::valid(...), ruleDescription: 'a duration such as "3 days"'),
                    new PropertyDefinition(self::Escalate, Type::string(), rule: static fn (mixed $value): bool => self::implements($value, AssignmentResolver::class), ruleDescription: 'a class implementing '.AssignmentResolver::class),
                    new PropertyDefinition(self::SelfApproval, Type::bool()),
                    new PropertyDefinition(self::Delegation, Type::bool(), default: false),
                    new PropertyDefinition(self::Abstain, Type::bool(), default: false),
                ],
            )
            ->withAllowedChildren([Stage::class]);
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
