<?php

declare(strict_types=1);

namespace Gcego\Workflows;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Workflows\Approvals\Approval;
use Gcego\Workflows\Definitions\WorkflowNode;
use Gcego\Workflows\States\State;
use Gcego\Workflows\Transitions\Transition;
use Illuminate\Database\Eloquent\Model;

/**
 * A business process, declared once in PHP:
 *
 *     return new Workflow(
 *         'institution-registration',
 *         new State('draft'),
 *         new State('submitted'),
 *         new State('approved', terminal: true),
 *         new Transition('submit', 'draft', 'submitted'),
 *         new Transition('approve', 'submitted', 'approved'),
 *     );
 *
 * A gcego/registry definition of kind "workflow" ("workflow:institution-registration"),
 * discovered from app/GCEGo/Workflows. Version identifies its structure:
 * running instances keep the version they started with; an instance of
 * another version continues only when this version lists it in Compatible
 * (and its state still exists), never silently.
 *
 * Who may start one: the subject's policy ability StartAbility (default
 * "update") and the workflow's gcego/security requirements. Construction
 * never queries or runs anything.
 */
class Workflow extends WorkflowNode
{
    public const string Title = 'title';

    public const string Version = 'version';

    /** Versions whose running instances this definition can continue. */
    public const string Compatible = 'compatible';

    /** The first state (default: the first declared). */
    public const string Initial = 'initial';

    /** The subject model class, when the workflow is for one kind of record. */
    public const string Subject = 'subject';

    public const string StartAbility = 'start_ability';

    /** The subject policy ability to see instances and their history (default "view"). */
    public const string ViewAbility = 'view_ability';

    /** The subject policy ability to cancel, suspend, resume and reassign (default "update"). */
    public const string ManageAbility = 'manage_ability';

    /** Several running instances per subject (default: one at a time). */
    public const string Concurrent = 'concurrent';

    public function workflowName(): string
    {
        return $this->nodeName();
    }

    public function title(): string
    {
        return $this->text(self::Title) ?? $this->label();
    }

    public function version(): int
    {
        return $this->integer(self::Version) ?? 1;
    }

    /**
     * @return list<int>
     */
    public function compatibleVersions(): array
    {
        $versions = $this->get(self::Compatible);

        return is_array($versions) ? array_values(array_filter($versions, is_int(...))) : [];
    }

    public function initialState(): string
    {
        return $this->text(self::Initial) ?? ($this->states()[0] ?? null)?->nodeName() ?? '';
    }

    /**
     * @return class-string<Model>|null
     */
    public function subjectClass(): ?string
    {
        $class = $this->get(self::Subject);

        return is_string($class) && is_subclass_of($class, Model::class) ? $class : null;
    }

    public function startAbility(): string
    {
        return $this->text(self::StartAbility) ?? 'update';
    }

    public function viewAbility(): string
    {
        return $this->text(self::ViewAbility) ?? 'view';
    }

    public function manageAbility(): string
    {
        return $this->text(self::ManageAbility) ?? 'update';
    }

    public function allowsConcurrentInstances(): bool
    {
        return $this->get(self::Concurrent) === true;
    }

    /**
     * @return list<State>
     */
    public function states(): array
    {
        return $this->childrenOf(State::class);
    }

    public function state(string $name): ?State
    {
        foreach ($this->states() as $state) {
            if ($state->nodeName() === $name) {
                return $state;
            }
        }

        return null;
    }

    /**
     * @return list<Transition>
     */
    public function transitions(): array
    {
        return $this->childrenOf(Transition::class);
    }

    /**
     * The transition of this name that leaves this state (declaration order), or null.
     */
    public function transition(string $name, string $from): ?Transition
    {
        foreach ($this->transitions() as $transition) {
            if ($transition->nodeName() === $name && $transition->leaves($from)) {
                return $transition;
            }
        }

        return null;
    }

    /**
     * Transitions leaving a state.
     *
     * @return list<Transition>
     */
    public function transitionsFrom(string $state): array
    {
        return array_values(array_filter($this->transitions(), static fn (Transition $transition): bool => $transition->leaves($state)));
    }

    /**
     * @return list<Approval>
     */
    public function approvals(): array
    {
        return $this->childrenOf(Approval::class);
    }

    public function approval(string $name): ?Approval
    {
        foreach ($this->approvals() as $approval) {
            if ($approval->nodeName() === $name) {
                return $approval;
            }
        }

        return null;
    }

    /**
     * A fingerprint of the structure (states and transitions), stored on instances.
     */
    public function structureHash(): string
    {
        $states = array_map(static fn (State $state): string => $state->nodeName().($state->isTerminal() ? '!' : ''), $this->states());
        $transitions = array_map(static fn (Transition $transition): string => $transition->nodeName().':'.implode(',', $transition->from()).'>'.$transition->to(), $this->transitions());

        return substr(hash('sha256', implode('|', [...$states, '#', ...$transitions])), 0, 16);
    }

    /**
     * Structural problems (errors). Unreachable states are reported by the inspector, not refused.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];
        $states = array_map(static fn (State $state): string => $state->nodeName(), $this->states());

        if ($states === []) {
            return ['a workflow needs at least one State.'];
        }

        foreach (array_count_values($states) as $state => $count) {
            if ($count > 1) {
                $problems[] = sprintf('the state "%s" is declared twice.', $state);
            }
        }

        if (! in_array($this->initialState(), $states, true)) {
            $problems[] = sprintf('the initial state "%s" is not declared.', $this->initialState());
        }

        $seen = [];

        foreach ($this->transitions() as $transition) {
            foreach ($transition->from() as $from) {
                $key = $transition->nodeName().'@'.$from;

                if (isset($seen[$key])) {
                    $problems[] = sprintf('the transition "%s" leaves "%s" twice.', $transition->nodeName(), $from);
                }

                $seen[$key] = true;

                if ($from !== '*' && ! in_array($from, $states, true)) {
                    $problems[] = sprintf('the transition "%s" leaves the unknown state "%s".', $transition->nodeName(), $from);
                } elseif ($from !== '*' && ! $transition->reopens() && $this->state($from)?->isTerminal() === true) {
                    $problems[] = sprintf('the transition "%s" leaves the terminal state "%s"; mark it Reopen to allow that.', $transition->nodeName(), $from);
                }
            }

            foreach ([$transition->to(), ...array_map(static fn ($branch): string => $branch->to(), $transition->branches())] as $to) {
                if (! in_array($to, $states, true)) {
                    $problems[] = sprintf('the transition "%s" goes to the unknown state "%s".', $transition->nodeName(), $to);
                }
            }

            if ($transition->requiredApproval() !== null && $this->approval((string) $transition->requiredApproval()) === null) {
                $problems[] = sprintf('the transition "%s" requires the unknown approval "%s".', $transition->nodeName(), $transition->requiredApproval());
            }

            if (! $transition->allowsHumans() && $transition->systemActors() === [] && ! in_array($transition->nodeName(), $this->outcomeTransitions(), true)) {
                $problems[] = sprintf('the transition "%s" allows neither people nor system actors.', $transition->nodeName());
            }
        }

        $names = array_unique(array_map(static fn (Transition $transition): string => $transition->nodeName(), $this->transitions()));
        $approvalNames = [];

        foreach ($this->approvals() as $approval) {
            if (isset($approvalNames[$approval->nodeName()])) {
                $problems[] = sprintf('the approval "%s" is declared twice.', $approval->nodeName());
            }

            $approvalNames[$approval->nodeName()] = true;

            if ($approval->state() !== '' && ! in_array($approval->state(), $states, true)) {
                $problems[] = sprintf('the approval "%s" opens in the unknown state "%s".', $approval->nodeName(), $approval->state());
            }

            foreach ([$approval->onApprove(), $approval->onReject(), $approval->onChanges()] as $outcome) {
                if ($outcome !== null && ! in_array($outcome, $names, true)) {
                    $problems[] = sprintf('the approval "%s" names the unknown transition "%s".', $approval->nodeName(), $outcome);
                } elseif ($outcome !== null && $approval->state() !== '' && $this->transition($outcome, $approval->state()) === null) {
                    $problems[] = sprintf('the approval "%s" names "%s", which does not leave "%s".', $approval->nodeName(), $outcome, $approval->state());
                }
            }
        }

        foreach ($this->states() as $state) {
            foreach ($state->tasks() as $task) {
                if ($task->completes() !== null && $this->transition((string) $task->completes(), $state->nodeName()) === null) {
                    $problems[] = sprintf('the task "%s" completes "%s", which does not leave "%s".', $task->nodeName(), $task->completes(), $state->nodeName());
                }
            }

            $then = $state->deadline()?->then();

            if ($then !== null) {
                $transition = $this->transition($then, $state->nodeName());

                if ($transition === null) {
                    $problems[] = sprintf('the deadline of "%s" runs "%s", which does not leave it.', $state->nodeName(), $then);
                } elseif (! in_array('deadlines', $transition->systemActors(), true)) {
                    $problems[] = sprintf('the deadline of "%s" runs "%s", which must name the "deadlines" system actor.', $state->nodeName(), $then);
                }
            }
        }

        return $problems;
    }

    /**
     * Transitions the definition itself runs as system actors: approval outcomes ("approvals") and task completions ("tasks").
     *
     * @return list<string>
     */
    public function outcomeTransitions(): array
    {
        $names = [];

        foreach ($this->approvals() as $approval) {
            array_push($names, ...array_filter([$approval->onApprove(), $approval->onReject(), $approval->onChanges()], is_string(...)));
        }

        foreach ($this->states() as $state) {
            foreach ($state->tasks() as $task) {
                if ($task->completes() !== null) {
                    $names[] = (string) $task->completes();
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * States no path from the initial state reaches (diagnostics; not an error).
     *
     * @return list<string>
     */
    public function unreachableStates(): array
    {
        $reached = [$this->initialState() => true];
        $queue = [$this->initialState()];

        while ($queue !== []) {
            $state = array_shift($queue);

            foreach ($this->transitionsFrom($state) as $transition) {
                foreach ([$transition->to(), ...array_map(static fn ($branch): string => $branch->to(), $transition->branches())] as $to) {
                    if (! isset($reached[$to])) {
                        $reached[$to] = true;
                        $queue[] = $to;
                    }
                }
            }
        }

        return array_values(array_diff(array_map(static fn (State $state): string => $state->nodeName(), $this->states()), array_keys($reached)));
    }

    protected static function namedArguments(): array
    {
        return [...parent::namedArguments(), 'title' => self::Title, 'version' => self::Version, 'compatible' => self::Compatible, 'initial' => self::Initial, 'subject' => self::Subject, 'startAbility' => self::StartAbility, 'viewAbility' => self::ViewAbility, 'manageAbility' => self::ManageAbility, 'concurrent' => self::Concurrent];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $ability = static fn (string $key): PropertyDefinition => new PropertyDefinition($key, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[A-Za-z][A-Za-z0-9_.:-]{0,99}$/', $value) === 1, ruleDescription: 'a policy ability');

        return parent::defineSchema()
            ->withType('workflows.workflow')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validName(...), ruleDescription: 'a lowercase name such as "institution-registration"'),
                new PropertyDefinition(self::Title, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && trim($value) !== '' && mb_strlen($value) <= 120, ruleDescription: 'a title of at most 120 characters'),
                new PropertyDefinition(self::Version, Type::int(), default: 1, rule: static fn (mixed $value): bool => is_int($value) && $value >= 1, ruleDescription: 'a positive version'),
                new PropertyDefinition(self::Compatible, Type::list(), rule: static fn (mixed $value): bool => is_array($value) && array_filter($value, static fn (mixed $version): bool => ! is_int($version) || $version < 1) === [], ruleDescription: 'a list of versions'),
                new PropertyDefinition(self::Initial, Type::string(), rule: self::validName(...), ruleDescription: 'a state name'),
                new PropertyDefinition(self::Subject, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && is_subclass_of($value, Model::class), ruleDescription: 'an Eloquent model class'),
                $ability(self::StartAbility), $ability(self::ViewAbility), $ability(self::ManageAbility),
                new PropertyDefinition(self::Concurrent, Type::bool(), default: false),
            )
            ->withAllowedChildren([State::class, Transition::class, Approval::class])
            ->withConstraint(static fn (ComponentInterface $workflow): array => $workflow instanceof self ? $workflow->problems() : []);
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Title];
    }
}
