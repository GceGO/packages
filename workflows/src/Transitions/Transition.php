<?php

declare(strict_types=1);

namespace Gcego\Workflows\Transitions;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Workflows\Automation\Automation;
use Gcego\Workflows\Contracts\TransitionGuard;
use Gcego\Workflows\Definitions\WorkflowNode;

/**
 * A transition between states:
 *
 *     new Transition('submit', 'draft', 'submitted')
 *     new Transition('publish', 'approved', 'published', guard: CanPublishContent::class)
 *     new Transition(name: 'cancel', from: ['draft', 'submitted'], to: 'cancelled', comment: 'required')
 *
 * Who may run it: the subject's Laravel policy Ability (default "update")
 * and any gcego/security requirements given to it. System transitions
 * (deadlines, automation, approval outcomes) run only for the system
 * actors they name. A transition that RequiresApproval runs only once that
 * approval is complete. Children: Branch (conditional destinations),
 * Automation (run after it, once committed).
 */
final class Transition extends WorkflowNode
{
    public const string From = 'from';

    public const string To = 'to';

    /** A TransitionGuard class. */
    public const string Guard = 'guard';

    /** The Laravel policy ability on the subject (default "update"). */
    public const string Ability = 'ability';

    /** Ask for confirmation in the UI. */
    public const string Confirmation = 'confirmation';

    /** none, optional or required. */
    public const string Comment = 'comment';

    /** The name of an Approval that must be complete first. */
    public const string RequiresApproval = 'requires_approval';

    /** System actor names allowed to run it ("deadlines", "automation", "approvals"); people never may when Human is false. */
    public const string SystemActors = 'system_actors';

    /** People may run it (default true; false for system-only transitions). */
    public const string Human = 'human';

    /** It may leave a terminal state. */
    public const string Reopen = 'reopen';

    /**
     * @return list<string>
     */
    public function from(): array
    {
        return $this->strings(self::From);
    }

    public function to(): string
    {
        return $this->text(self::To) ?? '';
    }

    public function leaves(string $state): bool
    {
        return in_array($state, $this->from(), true) || in_array('*', $this->from(), true);
    }

    /**
     * @return class-string<TransitionGuard>|null
     */
    public function guard(): ?string
    {
        $guard = $this->get(self::Guard);

        return is_string($guard) && is_subclass_of($guard, TransitionGuard::class) ? $guard : null;
    }

    public function ability(): string
    {
        return $this->text(self::Ability) ?? 'update';
    }

    public function needsConfirmation(): bool
    {
        return $this->get(self::Confirmation) === true;
    }

    public function comment(): string
    {
        return $this->text(self::Comment) ?? 'optional';
    }

    public function requiredApproval(): ?string
    {
        return $this->text(self::RequiresApproval);
    }

    /**
     * @return list<string>
     */
    public function systemActors(): array
    {
        return $this->strings(self::SystemActors);
    }

    public function allowsHumans(): bool
    {
        return $this->get(self::Human) !== false;
    }

    public function reopens(): bool
    {
        return $this->get(self::Reopen) === true;
    }

    /**
     * @return list<Branch>
     */
    public function branches(): array
    {
        return $this->childrenOf(Branch::class);
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
        return [...parent::namedArguments(), 'from' => self::From, 'to' => self::To, 'guard' => self::Guard, 'ability' => self::Ability, 'confirmation' => self::Confirmation, 'comment' => self::Comment, 'requiresApproval' => self::RequiresApproval, 'systemActors' => self::SystemActors, 'human' => self::Human, 'reopen' => self::Reopen];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $names = static fn (mixed $value): bool => (is_string($value) && ($value === '*' || self::validName($value))) || (is_array($value) && $value !== [] && array_filter($value, static fn (mixed $entry): bool => ! self::validName($entry) && $entry !== '*') === []);

        return parent::defineSchema()
            ->withType('workflows.transition')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validName(...), ruleDescription: 'a lowercase name such as "submit"'),
                new PropertyDefinition(self::From, Type::mixed(), required: true, rule: $names, ruleDescription: 'a state name, a list of state names, or "*"'),
                new PropertyDefinition(self::To, Type::string(), required: true, rule: self::validName(...), ruleDescription: 'a state name'),
                new PropertyDefinition(self::Guard, Type::string(), rule: static fn (mixed $value): bool => self::implements($value, TransitionGuard::class), ruleDescription: 'a class implementing '.TransitionGuard::class),
                new PropertyDefinition(self::Ability, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[A-Za-z][A-Za-z0-9_.:-]{0,99}$/', $value) === 1, ruleDescription: 'a policy ability'),
                new PropertyDefinition(self::Confirmation, Type::bool(), default: false),
                new PropertyDefinition(self::Comment, Type::string(), default: 'optional', allowedValues: ['none', 'optional', 'required']),
                new PropertyDefinition(self::RequiresApproval, Type::string(), rule: self::validName(...), ruleDescription: 'an approval name'),
                new PropertyDefinition(self::SystemActors, Type::list(), rule: static fn (mixed $value): bool => is_array($value) && array_filter($value, static fn (mixed $entry): bool => ! self::validName($entry)) === [], ruleDescription: 'system actor names'),
                new PropertyDefinition(self::Human, Type::bool(), default: true),
                new PropertyDefinition(self::Reopen, Type::bool(), default: false),
            )
            ->withAllowedChildren([Branch::class, Automation::class]);
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::From, self::To];
    }
}
